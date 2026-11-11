<?php

return [
    'dkim_generated' => 'DKIM keys generated successfully!',
    'dkim_not_verified' => 'DKIM record not found. Check your DNS configuration.',
    'dkim_verified' => 'DKIM verified successfully!',
    'invalid_ip_address' => 'Invalid IP address format.',
    'ip_added' => 'IP address added successfully!',
    'ip_deleted' => 'IP address deleted.',
    'ip_has_mailboxes' => 'Cannot delete IP address that has assigned mailboxes.',
    'ip_provisioned' => 'IP address provisioned successfully!',
    'pool_created' => 'IP pool created successfully!',
    'pool_deleted' => 'IP pool deleted.',
    'pool_max_ips_reached' => 'This pool has reached the maximum number of IP addresses.',
    'pool_not_empty' => 'Cannot delete a pool that contains IP addresses.',
    'provider_not_configured' => 'This provider is not configured. Please add your API key in provider settings.',
    'provider_saved' => 'Provider settings saved successfully!',
    'warming_started' => 'IP warming started!',
    'blacklist_checked' => 'Blacklist check completed.',

    // IP providers (addresses ordered through a hosting company's API) and reverse DNS
    'ip_release_failed' => 'The provider did not take the address back, so it would still be billed: :reason',
    'providers' => [
        'errors' => [
            'unreachable' => ':provider could not be reached. Try again in a moment.',
            'unauthorized' => ':provider did not accept the API key: :reason',
            'refused' => ':provider refused the request: :reason',
            'server_not_found' => 'This server is not in your :provider account any more.',
            'digitalocean_unusable' => 'DigitalOcean cannot provide dedicated IPs for mail: it blocks SMTP on Reserved IPs and gives them no reverse DNS.',
        ],
    ],
    'ptr' => [
        'verified' => 'Reverse DNS is correct: the address names its host and the host points back.',
        'not_verified' => 'Reverse DNS is not correct yet.',
        'set' => 'Reverse DNS was set at the provider.',
        'not_supported' => 'Reverse DNS of this address cannot be set from NetSendo. Set it in the panel of the company the address comes from.',
        'no_hostname' => 'The address has no host name to point back at.',
    ],
    'provision' => [
        'hostname' => 'host name',
        'server' => 'server',
    ],
];
