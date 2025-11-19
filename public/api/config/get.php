<?php
require_once '../config.php';

handleCORS();

// Return public configuration values to the frontend
// These are non-sensitive settings that the frontend needs to know

$config = [
    'success' => true,
    'config' => [
        // API Settings
        'api_base_url' => '/api',

        // Pagination
        'items_per_page' => ITEMS_PER_PAGE,

        // Currency
        'copper_to_platinum' => COPPER_TO_PLATINUM,

        // Refresh
        'refresh_interval_ms' => REFRESH_INTERVAL_SECONDS * 1000,

        // Icons
        'icon_base_url' => ICON_BASE_URL,
        'default_icon' => DEFAULT_ICON,
        'enable_item_icons' => filter_var(env('ENABLE_ITEM_ICONS', 'true'), FILTER_VALIDATE_BOOLEAN),

        // Alternate Currency (if enabled)
        'use_alt_currency' => USE_ALT_CURRENCY,
        'alt_currency' => USE_ALT_CURRENCY ? [
            'item_id' => ALT_CURRENCY_ITEM_ID,
            'value_platinum' => ALT_CURRENCY_VALUE_PLATINUM,
            'name' => ALT_CURRENCY_NAME
        ] : null,

        // Storage Keys (for localStorage)
        'storage_keys' => [
            'user' => 'eqemu_user',
            'token' => 'eqemu_token',
            'characters' => 'eqemu_characters'
        ],

        // Inventory Slots
        'inventory_slots' => [
            23 => 'General Slot 1',
            24 => 'General Slot 2',
            25 => 'General Slot 3',
            26 => 'General Slot 4',
            27 => 'General Slot 5',
            28 => 'General Slot 6',
            29 => 'General Slot 7',
            30 => 'General Slot 8'
        ]
    ]
];

sendJSON($config);
?>
