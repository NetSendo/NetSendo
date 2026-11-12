'use strict';

// The port 25 check of plugins/netsendo_outbound.js (XNMI-EGRESS), against fake mail
// servers on this machine. No Haraka needed:
//
//   node --test docker/haraka/test/egress.test.js

const assert = require('node:assert/strict');
const Module = require('node:module');
const net = require('node:net');
const path = require('node:path');
const test = require('node:test');

// haraka-net-utils only exists inside the MTA image; the check does not use it
const load = Module._load;
Module._load = function (request, ...rest) {
    if (request === 'haraka-net-utils') return {};
    return load.call(this, request, ...rest);
};

const pluginModule = require(path.join(__dirname, '..', 'plugins', 'netsendo_outbound.js'));

// A plugin instance as Haraka would make it, talking to `port` instead of 25
function plugin(port) {
    const instance = Object.create(pluginModule);
    Object.assign(instance, {
        loginfo() {},
        logerror() {},
        logwarn() {},
        config: { get: () => ({ main: {} }) },
        bind_map: {},
        default_helo: null,
        egress: { port25: 'pending' },
        probe_hosts: ['127.0.0.1'],
    });

    const connect = net.connect;
    instance.smtp_hello = function (host) {
        net.connect = (options) => connect({ ...options, port });
        try {
            return pluginModule.smtp_hello.call(this, host);
        } finally {
            net.connect = connect;
        }
    };

    return instance;
}

// A mail server that greets and names the client in its EHLO reply, like Gmail's MX
function fakeMx(reply) {
    const sockets = new Set();
    const server = net.createServer((socket) => {
        sockets.add(socket);
        socket.on('error', () => {});
        socket.on('close', () => sockets.delete(socket));
        socket.write('220-fake.example ESMTP\r\n220 ready\r\n');
        socket.on('data', (data) => {
            if (data.toString().startsWith('EHLO')) socket.write(reply);
        });
    });

    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => resolve({
            port: server.address().port,
            close: () => {
                for (const socket of sockets) socket.destroy();
                return new Promise((done) => server.close(done));
            },
        }));
    });
}

test('an open port 25 and the address the receiver saw', async () => {
    const mx = await fakeMx('250-fake.example at your service, [127.0.0.1]\r\n250-SIZE 1000\r\n250 SMTPUTF8\r\n');
    try {
        const instance = plugin(mx.port);
        const egress = await instance.probe_egress();

        assert.equal(egress.port25, 'open');
        assert.equal(egress.public_ip, '127.0.0.1');
        assert.match(instance.egress_capability(), /^XNMI-EGRESS port25=open ip=127\.0\.0\.1 ptr=\w+ checked=\d+$/);
    } finally {
        await mx.close();
    }
});

test('a refused connection reads as a blocked port', async () => {
    // A port nothing listens on
    const server = net.createServer();
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const { port } = server.address();
    await new Promise((resolve) => server.close(resolve));

    const egress = await plugin(port).probe_egress();

    assert.equal(egress.port25, 'blocked');
    assert.equal(egress.public_ip, null);
});

test('a host name that does not resolve says nothing about the port', async () => {
    const instance = plugin(25);
    instance.probe_hosts = ['no-such-host.invalid'];

    const egress = await instance.probe_egress();

    assert.equal(egress.port25, 'unknown');
});

test('NMI_HELO_HOST wins over the PTR name', () => {
    const instance = plugin(25);
    instance.egress = { port25: 'open', public_ip: '203.0.113.10', ptr: 'confirmed', helo: 'ptr.example.net', checked: 1 };
    assert.equal(instance.server_helo(), 'ptr.example.net');
    assert.match(instance.egress_capability(), / helo=ptr\.example\.net helo_from=ptr /);

    instance.default_helo = 'mail.example.com';
    assert.equal(instance.server_helo(), 'mail.example.com');
    assert.match(instance.egress_capability(), / helo=mail\.example\.com helo_from=env /);
});

test('a PTR name counts only when it resolves back to the address', async () => {
    const dns = require('node:dns').promises;
    const { reverse, resolve4 } = dns;
    dns.reverse = async () => ['wrong.example.net', 'right.example.net'];
    dns.resolve4 = async (name) => (name === 'right.example.net' ? ['203.0.113.10'] : ['198.51.100.1']);
    try {
        const result = await plugin(25).confirmed_ptr('203.0.113.10');
        assert.equal(result.confirmed, 'right.example.net');

        dns.resolve4 = async () => ['198.51.100.1'];
        assert.equal((await plugin(25).confirmed_ptr('203.0.113.10')).confirmed, null);
    } finally {
        dns.reverse = reverse;
        dns.resolve4 = resolve4;
    }
});
