// =============================================================================
// netsendo_outbound: the address and the name NetSendo's mail leaves with
// =============================================================================
// Decided in NetSendo and written to the login file (netsendo_auth.ini users_file),
// so nothing about it is set here:
//
//   {"version": 1, "helo": "netsendo.example.com",
//    "users": {"nmi-3-k2j4h5g6": {"sha256": "...", "domain": "example.com",
//                                  "ips": {"203.0.113.10": "mail1.example.com"}}}}
//
// A message names its dedicated IP in the X-NMI-IP header (NmiProvider). It leaves from
// that IP, introducing itself (HELO) with the IP's name, when the IP is one of the
// login's and is configured on this server (an address of a network interface the MTA
// sees: with network_mode: host, every address of the server). Otherwise it leaves from
// the server's main address under "helo", the name NetSendo picked (NMI_HOSTNAME or the
// panel's domain). Haraka's outbound takes both from transaction.notes (outbound_ip,
// outbound_helo) and keeps them in the queue for retries.
//
// Clients on the private network learn in the EHLO answer which addresses the MTA can
// send from (X-NETSENDO-ADDRESS), so the panel can show whether a dedicated IP is used.
// =============================================================================

const fs = require('node:fs');
const net = require('node:net');
const os = require('node:os');

const HOST_NAME = /^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/i;

exports.register = function () {
    this.load_netsendo_auth_ini();
};

exports.load_netsendo_auth_ini = function () {
    this.cfg = this.config.get('netsendo_auth.ini', () => this.load_netsendo_auth_ini());
    this.users_file = this.cfg.main?.users_file || '/opt/haraka/auth/mta-users.json';
};

exports.read_file = function () {
    try {
        const data = JSON.parse(fs.readFileSync(this.users_file, 'utf8'));
        return data && typeof data === 'object' ? data : {};
    } catch (err) {
        this.logerror(`cannot read ${this.users_file}: ${err.message}`);
        return {};
    }
};

// The address in the form NetSendo writes it (IPv6 shortened, lower case), or null
exports.normalize_ip = function (value) {
    const ip = String(value || '').trim().replace(/^\[|\]$/g, '');
    if (net.isIPv4(ip)) return ip;
    if (!net.isIPv6(ip)) return null;
    try {
        return new URL(`http://[${ip}]/`).hostname.slice(1, -1);
    } catch {
        return null;
    }
};

exports.host_name = function (value) {
    const name = String(value || '').trim().replace(/\.$/, '').toLowerCase();
    return HOST_NAME.test(name) && !net.isIP(name) ? name : null;
};

// Addresses of the network interfaces the MTA can send from
exports.local_addresses = function () {
    const addresses = new Set();
    for (const list of Object.values(os.networkInterfaces())) {
        for (const address of list || []) {
            if (address.internal) continue;
            const ip = this.normalize_ip(address.address);
            if (!ip || ip.startsWith('fe80:')) continue;
            addresses.add(ip);
        }
    }
    return addresses;
};

exports.hook_capabilities = function (next, connection) {
    if (connection.remote.is_private) {
        connection.capabilities.push('X-NETSENDO-MTA 1');
        for (const ip of this.local_addresses()) {
            connection.capabilities.push(`X-NETSENDO-ADDRESS ${ip}`);
        }
    }
    next();
};

exports.hook_queue_outbound = function (next, connection) {
    const txn = connection.transaction;
    const user = connection.notes.auth_user;
    if (!txn || !connection.relaying || !user) return next();

    const data = this.read_file();
    const users = data.users && typeof data.users === 'object' ? data.users : {};
    const entry = Object.prototype.hasOwnProperty.call(users, user) ? users[user] : null;
    const ips = entry && entry.ips && typeof entry.ips === 'object' ? entry.ips : {};
    const default_name = this.host_name(data.helo);

    let ip = null;
    let name = default_name;
    const wanted = this.normalize_ip(txn.header.get('X-NMI-IP'));

    if (wanted) {
        if (!Object.prototype.hasOwnProperty.call(ips, wanted)) {
            connection.logwarn(this, `${wanted} is not an IP of ${user}'s domain: sending from the main address`);
        }
        else if (!net.isIPv4(wanted)) {
            // outbound delivers over IPv4 (outbound.ini ipv6_enabled is off)
            connection.loginfo(this, `${wanted} is IPv6, mail is delivered over IPv4: sending from the main address`);
        }
        else if (!this.local_addresses().has(wanted)) {
            connection.logwarn(this, `${wanted} is not configured on this server: sending from the main address`);
        }
        else {
            ip = wanted;
            name = this.host_name(ips[wanted]) || default_name;
        }
    }

    if (ip) txn.notes.outbound_ip = ip;
    if (name) txn.notes.outbound_helo = name;

    connection.loginfo(this, `${user}: sending from ${ip || 'the main address'} as ${name || 'config/me'}`);
    next();
};
