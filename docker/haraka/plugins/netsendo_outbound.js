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
// Mail without a dedicated IP leaves from the server's own address. Its HELO name is
// NMI_HELO_HOST when set, else the address's PTR name when that name resolves back to
// the address (see "Outbound port 25" below), else the container's host name, which
// other servers cannot resolve.
//
// Outbound port 25 and the server's public address (XNMI-EGRESS)
//
// Whether mail can leave at all depends on two things NetSendo cannot see from inside
// a container: whether the hosting provider lets port 25 out (many block it for new
// accounts), and which public address receivers see (behind NAT a container knows a
// private one only). The MTA finds both out as a receiver would: it opens an SMTP
// session to a large provider's MX, which names the client's address in its EHLO reply
// ("250-mx.google.com at your service, [203.0.113.10]"), and leaves with QUIT, sending
// nothing. It does so at start and then every few hours, and tells NetSendo the result
// in the EHLO response (XNMI-EGRESS), so the panel can show what to set up: port 25,
// the PTR record of the address and the address for the domains' SPF records.
// NMI_EGRESS_PROBE=false turns the check off; NMI_EGRESS_PROBE_HOSTS replaces the
// servers it talks to (comma-separated host names, port 25).

const cluster = require('node:cluster');
const dns = require('node:dns').promises;
const net = require('node:net');
const os = require('node:os');

const net_utils = require('haraka-net-utils');

const INTERNAL_HEADERS = ['X-NMI-IP', 'X-NMI-Helo', 'X-NMI-Domain'];
const HOSTNAME = /^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i;
const CAPABILITY = 'XNMI-BIND';
const CAPABILITY_LINE_LENGTH = 400;

const EGRESS_CAPABILITY = 'XNMI-EGRESS';
const PROBE_HOSTS = ['gmail-smtp-in.l.google.com', 'alt1.gmail-smtp-in.l.google.com', 'alt2.gmail-smtp-in.l.google.com'];
const PROBE_TIMEOUT_MS = 15 * 1000;
const PROBE_FIRST_MS = 5 * 1000;
// Next check: open 6 h, blocked 1 h, anything else (DNS trouble, no answer) 10 min
const PROBE_AGAIN_MS = { open: 6 * 3600 * 1000, blocked: 3600 * 1000 };
const PROBE_RETRY_MS = 10 * 60 * 1000;

exports.register = function () {
    this.bind_map = this.parse_bind_map(process.env.NMI_BIND_MAP);
    this.default_helo = this.valid_helo(process.env.NMI_HELO_HOST);

    const mapped = Object.keys(this.bind_map).length;
    this.loginfo(`can send from: ${this.sendable_addresses().join(', ') || '(no address)'}${mapped ? ` (${mapped} mapped)` : ''}`);

    this.probe_hosts = String(process.env.NMI_EGRESS_PROBE_HOSTS || '')
        .split(/[\s,;]+/)
        .filter((host) => HOSTNAME.test(host) || net.isIP(host));
    if (this.probe_hosts.length === 0) this.probe_hosts = PROBE_HOSTS;

    this.egress = { port25: 'pending', public_ip: null, ptr: null, helo: null, checked: null };

    if (String(process.env.NMI_EGRESS_PROBE || '').trim().toLowerCase() === 'false') {
        this.egress.port25 = 'off';
    } else if (!this.is_cluster_master()) {
        // The workers answer the sessions; a cluster's master process never does
        this.schedule_probe(PROBE_FIRST_MS);
    }
};

// With `nodes` set (smtp.ini), Haraka runs as a cluster whose master only forks workers
exports.is_cluster_master = function () {
    if (!cluster.isPrimary) return false;

    const nodes = String(this.config.get('smtp.ini')?.main?.nodes ?? '').trim();

    return nodes !== '' && nodes !== '0';
};

exports.schedule_probe = function (delay) {
    const timer = setTimeout(() => {
        this.probe_egress()
            .catch((err) => this.logerror(`port 25 check failed: ${err.message}`))
            .finally(() => this.schedule_probe(PROBE_AGAIN_MS[this.egress.port25] ?? PROBE_RETRY_MS));
    }, delay);
    timer.unref?.();
};

// Port 25 to the outside, the public address and its PTR name, as one result
exports.probe_egress = async function () {
    const failures = [];
    let reached = false;
    let address = null;

    for (const host of this.probe_hosts) {
        const result = await this.smtp_hello(host);
        if (result.greeted) {
            reached = true;
            address = this.normalise_ip(result.client_ip);
            if (address) break;
        } else {
            failures.push(result.error);
        }
    }

    // Timeouts and refusals everywhere: the provider drops port 25. A DNS failure says
    // nothing about the port, so it is not taken for a block.
    const blocked = !reached && failures.length > 0
        && failures.every((error) => ['timeout', 'refused', 'network'].includes(error));

    const egress = {
        port25: reached ? 'open' : (blocked ? 'blocked' : 'unknown'),
        public_ip: address,
        ptr: null,
        helo: null,
        checked: Math.floor(Date.now() / 1000),
    };

    if (address) {
        const reverse = await this.confirmed_ptr(address);
        egress.ptr = reverse.confirmed ? 'confirmed' : (reverse.names.length ? 'unconfirmed' : 'none');
        egress.helo = reverse.confirmed;
    }

    const changed = egress.port25 !== this.egress.port25 || egress.public_ip !== this.egress.public_ip
        || egress.helo !== this.egress.helo;
    this.egress = egress;

    if (changed) {
        this.loginfo(`port 25 out: ${egress.port25}`
            + (address ? `, public address ${address}, PTR ${egress.ptr}${egress.helo ? ` (${egress.helo})` : ''}` : '')
            + (this.default_helo ? `, HELO ${this.default_helo} (NMI_HELO_HOST)` : ''));
    }

    return egress;
};

// One SMTP greeting and EHLO, then QUIT. Resolves with whether the server greeted us
// and the client address it named, never rejects.
exports.smtp_hello = function (host) {
    return new Promise((resolve) => {
        let buffer = '';
        let stage = 'greeting';
        let greeted = false;
        let client_ip = null;
        let done = false;

        const socket = net.connect({ host, port: 25 });

        const finish = (error = null) => {
            if (done) return;
            done = true;
            socket.destroy();
            resolve({ greeted, client_ip, error: greeted ? null : error });
        };

        socket.setTimeout(PROBE_TIMEOUT_MS, () => finish('timeout'));
        socket.on('error', (err) => {
            if (['ENOTFOUND', 'EAI_AGAIN', 'ESERVFAIL'].includes(err.code)) return finish('dns');
            if (err.code === 'ECONNREFUSED') return finish('refused');
            finish('network');
        });
        socket.on('close', () => finish('closed'));
        socket.on('data', (chunk) => {
            buffer += chunk.toString('latin1');

            let end;
            while ((end = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, end).replace(/\r$/, '');
                buffer = buffer.slice(end + 1);
                const last = line.length < 4 || line[3] === ' ';

                if (stage === 'greeting') {
                    if (!line.startsWith('220')) return finish('greeting');
                    if (!last) continue;
                    greeted = true;
                    stage = 'ehlo';
                    socket.write(`EHLO ${this.probe_name()}\r\n`);
                } else {
                    const named = line.match(/\[([0-9a-f:.]+)\]/i);
                    if (named && !client_ip) client_ip = named[1];
                    if (last) {
                        socket.end('QUIT\r\n');
                        return finish();
                    }
                }
            }
        });
    });
};

exports.probe_name = function () {
    return this.default_helo || this.egress?.helo || this.valid_helo(os.hostname()) || 'localhost.localdomain';
};

// The PTR name of an address when it resolves back to that address, else null
exports.confirmed_ptr = async function (ip) {
    let names = [];
    try {
        names = await dns.reverse(ip);
    } catch (err) {
        return { names: [], confirmed: null };
    }

    for (const raw of names) {
        const name = this.valid_helo(raw);
        if (!name) continue;

        try {
            const addresses = net.isIPv6(ip) ? await dns.resolve6(name) : await dns.resolve4(name);
            if (addresses.some((address) => this.normalise_ip(address) === ip)) {
                return { names, confirmed: name };
            }
        } catch (err) {
            // no address for this name: try the next one
        }
    }

    return { names, confirmed: null };
};

// HELO name of mail without a dedicated IP: NMI_HELO_HOST, else the confirmed PTR name
exports.server_helo = function () {
    return this.default_helo || this.egress?.helo || null;
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

    connection.capabilities.push(this.egress_capability());

    next();
};

// "XNMI-EGRESS port25=open ip=203.0.113.10 ptr=confirmed helo=mta.example.com helo_from=ptr checked=1731400000"
exports.egress_capability = function () {
    const egress = this.egress || {};
    const helo = this.server_helo();
    const parts = [EGRESS_CAPABILITY, `port25=${egress.port25 || 'pending'}`];

    if (egress.public_ip) parts.push(`ip=${egress.public_ip}`);
    if (egress.ptr) parts.push(`ptr=${egress.ptr}`);
    if (helo) parts.push(`helo=${helo}`, `helo_from=${this.default_helo ? 'env' : 'ptr'}`);
    if (egress.checked) parts.push(`checked=${egress.checked}`);

    return parts.join(' ');
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

    const server_helo = this.server_helo();
    if (server_helo) this.set_helo(txn, server_helo);

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
