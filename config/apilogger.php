<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Logger Driver
    |--------------------------------------------------------------------------
    | Supported: "file", "db"
    | You may also supply a fully qualified class name of your own driver
    | that implements \GDI\ApiLogger\Contracts\ApiLoggerInterface
    |
    */
    'driver' => env('APILOGGER_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log File Path
    |--------------------------------------------------------------------------
    | Used when driver is set to "file". Path is relative to storage_path().
    |
    */
    'log_file' => 'logs/apilogger.log',

    /*
    |--------------------------------------------------------------------------
    | Max Log Entries (file driver)
    |--------------------------------------------------------------------------
    | Maximum number of log entries kept in the file. Older entries are
    | pruned automatically. Set to 0 for unlimited.
    |
    */
    'max_entries' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Hidden Fields
    |--------------------------------------------------------------------------
    | These request payload fields will be replaced with "***" in the logs.
    |
    */
    'hidden_fields' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'credit_card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Response Body
    |--------------------------------------------------------------------------
    | Whether to capture and store the response body.
    | Disable for high-traffic routes to save storage.
    |
    */
    'log_response' => env('APILOGGER_LOG_RESPONSE', true),

    /*
    |--------------------------------------------------------------------------
    | Max Response Body Length
    |--------------------------------------------------------------------------
    | Truncate response body stored in logs to this many characters.
    | Set to 0 for no limit.
    |
    */
    'max_response_length' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route Configuration
    |--------------------------------------------------------------------------
    | Configure the prefix and middleware for the dashboard routes.
    |
    */
    'route' => [
        'prefix'     => 'apilogger',
        'middleware' => ['web'], // Add 'auth' or other middleware as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Page Results on Dashboard
    |--------------------------------------------------------------------------
    */
    'per_page' => 20,

];
