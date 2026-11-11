'use strict';

// Outbound address of NetSendo Mail Infrastructure (NMI) mail.
//
// A mailbox with a dedicated IP sends two internal headers with every message:
//
//   X-NMI-IP:   the dedicated IP address the mail must leave from
//   X-NMI-Helo: the host name of that address (what its PTR record points to)
//
// For a relayed message this plugin makes Haraka's outbound delivery use them and
// removes them, together with X-NMI-Domain, before delivery:
//
//   - the address through transaction.notes.outbound_ip, which outbound/hmail.js reads;
//   - the HELO name through the get_mx hook. Haraka has a note for it as well
//     (outbound_helo), but every MX it looks up already carries the MTA's own name
//     (haraka-net-utils HarakaMx), so the note is never used.
//
// The MTA can only send from an address it owns:
//
//   - an address of one of its network interfaces (the container shares the host's
//     network, see docker-compose.nmi-host.yml), or
//   - an address listed in NMI_BIND_MAP, for providers where the public address is
//     translated to a private one on the server (DigitalOcean anchor IPs, Google
//     Cloud, AWS):  NMI_BIND_MAP="203.0.113.10=10.19.0.5,203.0.113.11=10.19.0.6"
//
// Mail for any other address still goes out, from the MTA's default address, with a
// warning in the log. The addresses the MTA can send from are advertised to NetSendo
// in the EHLO response (XNMI-BIND), so the mailbox form can say which case applies.
//
// NMI_HELO_HOST sets the HELO name of mail without a dedicated IP (default: the
// container's host name, which is rarely a name other servers can resolve).

const net = require('node:net');
const os = require('node:os');

const net_utils = require('haraka-net-utils');

const INTERNAL_HEADERS = ['X-NMI-IP', 'X-NMI-Helo', 'X-NMI-Domain'];
const HOSTNAME = /^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i;
const CAPABILITY = 'XNMI-BIND';
const CAPABILITY_LINE_LENGTH = 400;

exports.register = function () {
    this.bind_map = this.parse_bind_map(process.env.NMI_BIND_MAP);
    this.default_helo = this.valid_helo(process.env.NMI_HELO_HOST);

    const mapped = Object.keys(this.bind_map).length;
    this.loginfo(`can send from: ${this.sendable_addresses().join(', ') || '(no address)'}${mapped ? ` (${mapped} mapped)` : ''}`);
};

// "public=local,public=local" -> { public: local }, both normalised
exports.parse_bind_map = function (value) {
    const map = {};

    for (const pair of String(value || '').split(/[\s,;]+/)) {
        if (!pair) continue;

        const [from, to] = pair.split('=').map((part) => this.normalise_ip(part));
        if (from && to) {
            map[from] = to;
        } else {
            this.logerror(`NMI_BIND_MAP: "${pair}" is not "public-address=local-address", ignored`);
        }
    }

    return map;
};

// One spelling per address, so "2001:DB8::1" and "2001:db8:0:0:0:0:0:1" compare equal
exports.normalise_ip = function (value) {
    const ip = String(value || '').trim();

    if (net.isIPv4(ip)) return ip;
    if (!net.isIPv6(ip)) return null;

    try {
        return new URL(`http://[${ip}]/`).hostname.slice(1, -1);
    } catch (err) {
        return null;
    }
};

exports.valid_helo = function (value) {
    const name = String(value || '').trim().replace(/\.$/, '');

    return HOSTNAME.test(name) ? name.toLowerCase() : null;
};

// Addresses of the MTA's own interfaces (read on every use: they can change at run time)
exports.local_addresses = function () {
    const addresses = new Set();

    for (const list of Object.values(os.networkInterfaces())) {
        for (const address of list || []) {
            if (address.internal) continue;

            const ip = this.normalise_ip(address.address);
            if (ip) addresses.add(ip);
        }
    }

    return addresses;
};

// The local address to bind to for mail that must leave from `ip`, or null
exports.bind_address = function (ip) {
    const local = this.local_addresses();
    const mapped = this.bind_map[ip];

    if (mapped) return local.has(mapped) ? mapped : null;

    return local.has(ip) ? ip : null;
};

// Every address NetSendo may ask for: the MTA's own, and the mapped public ones
exports.sendable_addresses = function () {
    const local = this.local_addresses();
    const addresses = new Set(local);

    for (const [from, to] of Object.entries(this.bind_map)) {
        if (local.has(to)) addresses.add(from);
    }

    return [...addresses].sort();
};

exports.hook_capabilities = function (next, connection) {
    // For NetSendo's own containers only, like AUTH (netsendo_auth)
    if (!connection.remote.is_private && !connection.tls.enabled) return next();

    let line = [];
    let length = 0;
    const flush = () => {
        connection.capabilities.push(`${CAPABILITY} ${line.join(',')}`.trim());
        line = [];
        length = 0;
    };

    for (const address of this.sendable_addresses()) {
        if (length + address.length > CAPABILITY_LINE_LENGTH) flush();
        line.push(address);
        length += address.length + 1;
    }
    flush();

    next();
};

exports.hook_data_post = function (next, connection) {
    const txn = connection.transaction;
    if (!txn) return next();

    const wanted = String(txn.header.get('X-NMI-IP') || '').trim();
    const helo = this.valid_helo(txn.header.get('X-NMI-Helo'));

    // Internal headers never leave the MTA, whoever sent them
    for (const name of INTERNAL_HEADERS) {
        if (txn.header.get(name)) txn.remove_header(name);
    }

    // Only NetSendo's logged-in sessions choose where mail leaves from
    if (!connection.relaying) return next();

    if (this.default_helo) this.set_helo(txn, this.default_helo);

    if (!wanted) return next();

    const ip = this.normalise_ip(wanted);
    const bind = ip ? this.bind_address(ip) : null;

    if (!bind) {
        connection.logwarn(this, `dedicated IP ${wanted} is not an address of this MTA (see NMI_BIND_MAP): sending from the default address`);
        return next();
    }

    txn.notes.outbound_ip = bind;
    if (helo) this.set_helo(txn, helo);

    connection.loginfo(this, `sending from ${ip}${bind !== ip ? ` (bound to ${bind})` : ''}${helo ? ` as ${helo}` : ''}`);

    next();
};

exports.set_helo = function (txn, name) {
    txn.notes.nmi_helo = name; // read by hook_get_mx below
    txn.notes.outbound_helo = name; // Haraka's own note, for versions that honour it
};

// The HELO name of a message: look the MX up the way Haraka does and hand it back with
// the name set. Anything unusual (a DNS error, no MX at all, an MX that is this server)
// is left to Haraka's own lookup, which bounces or retries as it always does.
exports.hook_get_mx = function (next, hmail, domain) {
    const helo = hmail.todo?.notes?.nmi_helo;
    if (!helo) return next();

    net_utils
        .get_mx(domain)
        .then(async (exchanges) => {
            const mxs = [];

            for (const mx of exchanges || []) {
                const local = await net_utils.is_local_host(mx.exchange).catch(() => null);
                if (local !== false) return next();

                mx.bind_helo = helo;
                mxs.push(mx);
            }

            if (mxs.length === 0) return next();

            next(OK, mxs);
        })
        .catch(() => next());
};
