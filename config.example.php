<?php

return [
    'app' => [
        'name' => 'XUI.ONE Customer Panel',

        // Customer panel URL. Leave empty for automatic detection.
        // Example: https://yourdomain.com/xpanel
        'base_url' => '',

        // Timezone used for date and time display.
        'timezone' => 'Europe/Istanbul',

        'debug' => false,
        'session_name' => 'xuione_customer_panel',

        // Short text shown inside the header logo mark.
        'logo_text' => 'X',
    ],

    'xui' => [
        // XUI.ONE panel URL. Include the port if your panel uses one.
        // Examples: https://panel.example.com:8080 or http://123.123.123.123:25461
        'base_url' => 'https://your-xui-panel.com:port',

        // Admin API access code created in XUI.ONE Access Control.
        'access_code' => 'YOUR_ADMIN_API_ACCESS_CODE',

        // Admin API key generated from the XUI.ONE administrator profile.
        'api_key' => 'YOUR_ADMIN_API_KEY',

        // Use true for valid SSL. Use false only for HTTP or self-signed SSL panels.
        'verify_ssl' => true,

        'timeout' => 15,
        'player_api_path' => 'player_api.php',
        'admin_http_method' => 'GET',
        'edit_http_method' => 'POST',
        'edit_http_methods' => ['POST', 'GET'],

        'line_lookup_action' => 'get_lines',
        'line_lookup_actions' => ['get_lines'],
        'line_id_keys' => ['id', 'line_id', 'user_id', 'member_id', 'lineid'],
        'line_username_keys' => ['username', 'user_name', 'member_username', 'line_username', 'login', 'user'],
        'line_bouquet_keys' => ['bouquet', 'bouquets', 'bouquets_selected', 'bouquet_ids'],
        'line_package_keys' => ['package_id', 'package', 'member_package_id', 'package_id_fk'],

        'package_get_actions' => ['get_package'],
        'package_list_actions' => ['get_packages', 'packages'],
        'package_id_keys' => ['id', 'package_id'],
        'package_bouquet_keys' => ['bouquet', 'bouquets', 'bouquets_selected', 'bouquet_ids', 'bouquets_ids'],

        'line_update_preserve_keys' => ['username', 'password', 'member_id', 'exp_date', 'max_connections', 'admin_enabled', 'enabled', 'is_trial', 'is_restreamer', 'admin_notes', 'reseller_notes', 'package_id', 'package'],
        'password_field' => 'password',

        'password_update_mode' => 'direct_sql_first',
        'bouquet_update_mode' => 'direct_sql_first',

        'reload_cache_after_update' => true,
        'verify_password_after_update' => false,

        'bouquet_payload' => [
            'field' => 'bouquet',
            'format' => 'json',
        ],

        // Change these values only if your XUI.ONE database uses a different table or column for bouquets.
        'direct_sql_bouquet_update' => [
            'enabled' => true,
            'tables' => ['lines'],
            'field' => 'bouquet',
            'fields' => ['bouquet'],
            'query_params' => ['query', 'sql'],
            'methods' => ['POST'],
        ],

        // Change these values only if your XUI.ONE database uses a different table or column for passwords.
        'direct_sql_password_update' => [
            'enabled' => true,
            'tables' => ['lines'],
            'field' => 'password',
            'fields' => ['password'],
            'query_params' => ['query', 'sql'],
            'methods' => ['POST'],
        ],
    ],

    'contact' => [
        // Contact menu item. Value can be an email address, Telegram link, WhatsApp link or support URL.
        'enabled' => true,
        'label' => 'Contact',
        'type' => 'auto',
        'value' => 'support@example.com',
    ],

    'playlist' => [
        // M3U playlist URL shown on the dashboard.
        'enabled' => true,
        'path' => 'get.php',
        'type' => 'm3u_plus',
        'output' => 'ts',
    ],

    'security' => [
        // Customer login and password rules.
        'login_max_attempts' => 6,
        'login_decay_minutes' => 10,
        'password_min' => 6,
        'password_max' => 14,
        'password_pattern' => '/^[A-Za-z0-9]+$/',
    ],

    'features' => [
        'show_raw_api_errors' => false,
    ],
];
