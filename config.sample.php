<?php

return [
    'sync' => [
        'enhance_host' => '', // Enhance CP domain, for example: cp.example.com
        'enhance_organization_id' => '', // In Enhance CP: Settings -> Access tokens -> orgId
        'enhance_api_key' => '',  // In Enhance CP: Settings -> Access tokens. Required level: System administrator
        'bunny_api_key' => '', // dash.bunny.net -> Account settings -> API key
        'custom_nameservers' => ['', ''], // Format: ['ns1.example.org', 'ns2.example.org']. Set to [] to use default Bunny.net nameservers
        'dns_zones_filter_mode' => 'blacklist', // 'blacklist' or 'whitelist'
        'dns_zones_list' => [],  // Format: ['example.org', 'example.net'] DNS zones (domains) to include (whitelist mode) or exclude from sync (blacklist mode)
        'skip_disabled_websites' => true
    ],
    'web' => [
        'username' => 'admin', // Enter this username in the HTTP authorization dialog
        'password' => '' // Enter this password in the HTTP authorization dialog
        // [!] Leaving 'password' field empty will disable the web UI
    ],
    // Main configuration ends here
    // Below are system defaults. Adjust only if needed
    'system' => [
        'dns_zones_folder' => APP_PATH . '/data/dns-zones',
        'log_path' => APP_PATH . '/data/sync.log',
        'debug' => false
    ]
];
