// =============================================================================
// netsendo_auth: SMTP AUTH against the logins NetSendo writes
// =============================================================================
// One login per domain with NetSendo MTA (NMI) switched on. NetSendo writes them to a
// JSON file on the nmi-auth volume (php artisan nmi:sync-mta-auth); this plugin reads the
// file at every AUTH, so a new or removed login needs no restart. The file holds SHA-256
// hashes of the passwords, not the passwords:
//
//   {"version": 1, "users": {"nmi-3-k2j4h5g6": {"sha256": "<hex>", "domain": "example.com"}}}
//
// A successful login lets the session relay (auth/auth_base sets connection.relaying);
// without one every outside recipient is refused, so the MTA is never an open relay.
// =============================================================================

const crypto = require('node:crypto');
const fs = require('node:fs');

exports.register = function () {
    this.inherits('auth/auth_base');
    // auth_base keeps the password in connection.notes unless told not to
    this.blankout_password = true;
    this.load_netsendo_auth_ini();
};

exports.load_netsendo_auth_ini = function () {
    this.cfg = this.config.get('netsendo_auth.ini', () => this.load_netsendo_auth_ini());
    this.users_file = this.cfg.main?.users_file || '/opt/haraka/auth/mta-users.json';
};

// AUTH only on the private Docker network or over TLS, never in clear text on the internet
exports.hook_capabilities = function (next, connection) {
    if (!connection.remote.is_private && !connection.tls.enabled) {
        connection.logdebug(this, 'AUTH not offered: public connection without TLS');
        return next();
    }

    const methods = ['PLAIN', 'LOGIN'];
    connection.capabilities.push(`AUTH ${methods.join(' ')}`);
    connection.notes.allowed_auth_methods = methods;
    next();
};

exports.read_users = function () {
    try {
        const data = JSON.parse(fs.readFileSync(this.users_file, 'utf8'));
        return data && typeof data.users === 'object' && data.users !== null ? data.users : {};
    } catch (err) {
        this.logerror(`cannot read ${this.users_file}: ${err.message}`);
        return {};
    }
};

exports.check_plain_passwd = function (connection, user, passwd, cb) {
    const users = this.read_users();
    const entry = Object.prototype.hasOwnProperty.call(users, user) ? users[user] : null;

    if (!entry || typeof entry.sha256 !== 'string' || !/^[0-9a-f]{64}$/i.test(entry.sha256)) {
        return cb(false);
    }

    const given = crypto.createHash('sha256').update(String(passwd), 'utf8').digest();
    const expected = Buffer.from(entry.sha256, 'hex');
    const valid = crypto.timingSafeEqual(given, expected);

    if (valid) {
        connection.loginfo(this, `NetSendo login ${user} (${entry.domain || 'unknown domain'})`);
    }
    cb(valid);
};
