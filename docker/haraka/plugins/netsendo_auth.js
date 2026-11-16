'use strict';

// SMTP AUTH for NetSendo Mail Infrastructure (NMI).
//
// NetSendo issues one SMTP login per sending domain (Settings -> Mailboxes, provider
// "NetSendo MTA") and writes all of them to a JSON file on a volume shared with this
// container:
//
//   { "users": { "<username>": "<sha256 of the password, hex>" } }
//
// The file holds hashes only, the passwords stay encrypted in NetSendo's database.
// It is read again whenever it changes, so a new or rotated login works without
// restarting the MTA. Only an authenticated session may relay; everything else is
// refused at RCPT TO, so the MTA is never an open relay.

const crypto = require('node:crypto');
const fs = require('node:fs');

const DEFAULT_AUTH_FILE = '/opt/haraka/netsendo/auth.json';
const RELOAD_ATTEMPTS = 3;
const RELOAD_DELAY_MS = 100;

exports.register = function () {
    this.inherits('auth/auth_base');

    this.auth_file = process.env.NMI_AUTH_FILE || DEFAULT_AUTH_FILE;
    this.users = {};
    this.loaded_signature = null;
    this.missing_reported = false;

    this.load_users();
};

// Reads the login file when it is new or has changed (NetSendo replaces it atomically).
// With `force` it is read whatever its size and time say.
exports.load_users = function (force = false) {
    let stat;
    try {
        stat = fs.statSync(this.auth_file);
    } catch (err) {
        if (!this.missing_reported) {
            this.logwarn(`NMI login file ${this.auth_file} is not there yet: no login is accepted (${err.code})`);
            this.missing_reported = true;
        }
        this.users = {};
        this.loaded_signature = null;
        return;
    }

    const signature = `${stat.ino}:${stat.size}:${stat.mtimeMs}`;
    if (!force && signature === this.loaded_signature) return;

    try {
        const data = JSON.parse(fs.readFileSync(this.auth_file, 'utf8'));
        this.users = data && typeof data.users === 'object' && data.users !== null ? data.users : {};
        this.missing_reported = false;
        if (signature !== this.loaded_signature) {
            this.loginfo(`loaded ${Object.keys(this.users).length} NMI login(s) from ${this.auth_file}`);
        }
        this.loaded_signature = signature;
    } catch (err) {
        // Keep the logins read before: a half-written or broken file must not stop sending
        this.logerror(`cannot read ${this.auth_file}: ${err.message}`);
    }
};

exports.hook_capabilities = function (next, connection) {
    // PLAIN sends the password as it is: offer it to NetSendo's own containers
    // (private network) and to encrypted sessions only.
    if (!connection.remote.is_private && !connection.tls.enabled) return next();

    // PLAIN only, on purpose. With LOGIN the password arrives on a line of its own,
    // which Haraka writes to the log at level "info" as an unrecognized command;
    // "AUTH PLAIN <credentials>" is logged as "AUTH".
    const methods = ['PLAIN'];
    connection.capabilities.push(`AUTH ${methods.join(' ')}`);
    connection.notes.allowed_auth_methods = methods;
    next();
};

exports.check_plain_passwd = function (connection, user, passwd, cb) {
    this.load_users();

    if (this.password_matches(user, passwd)) return cb(true);

    // Before refusing, read the file again a few times, whatever its size and time say.
    // On shared file systems (Docker Desktop bind mounts, network volumes) a file that
    // was replaced a moment ago can report its old attributes, or be missing, for a
    // few milliseconds. Only a failing login pays for this.
    let attempts = 0;
    const retry = () => {
        attempts++;
        this.load_users(true);

        if (this.password_matches(user, passwd)) return cb(true);
        if (attempts >= RELOAD_ATTEMPTS) return cb(false);

        setTimeout(retry, RELOAD_DELAY_MS);
    };

    setTimeout(retry, RELOAD_DELAY_MS);
};

exports.password_matches = function (user, passwd) {
    if (!Object.prototype.hasOwnProperty.call(this.users, user)) return false;

    const expected = Buffer.from(String(this.users[user]), 'hex');
    const given = crypto.createHash('sha256').update(String(passwd)).digest();

    return expected.length === given.length && crypto.timingSafeEqual(expected, given);
};
