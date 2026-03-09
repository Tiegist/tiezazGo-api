<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public menu domain for QR URLs
    |--------------------------------------------------------------------------
    |
    | This is the domain that will be encoded inside the QR code.
    | Example: https://menu.myapp.com
    |
    */
    'menu_domain' => env('QR_MENU_DOMAIN', 'https://menu.myapp.com'),

    /*
    |--------------------------------------------------------------------------
    | QR URL pattern
    |--------------------------------------------------------------------------
    |
    | Final URL becomes:
    |   {menu_domain}/{restaurant_slug}/table/{table_number}
    |
    */
    'table_path_template' => '{slug}/table/{table_number}',

    /*
    |--------------------------------------------------------------------------
    | QR image defaults
    |--------------------------------------------------------------------------
    */
    'default_format' => env('QR_DEFAULT_FORMAT', 'png'), // png|svg
    'size' => (int) env('QR_SIZE', 300),
    'margin' => (int) env('QR_MARGIN', 10),
    'error_correction' => env('QR_ERROR_CORRECTION', 'high'), // low|medium|quartile|high

    // Hex colors like #000000
    'foreground' => env('QR_FOREGROUND', '#000000'),
    'background' => env('QR_BACKGROUND', '#FFFFFF'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('QR_STORAGE_DISK', 'public'),
    'directory' => env('QR_STORAGE_DIR', 'qrcodes'),
];

