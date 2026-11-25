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
    | Queue Configuration
    |--------------------------------------------------------------------------
    | Enable async logging via Laravel queues to avoid blocking requests.
    |
    */
    'queue' => [
        'enabled' => env('APILOGGER_QUEUE_ENABLED', false),
        'connection' => env('APILOGGER_QUEUE_CONNECTION', 'default'),
        'queue' => env('APILOGGER_QUEUE_NAME', 'default'),
    ],

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
    | Max URL Length
    |--------------------------------------------------------------------------
    | Truncate URL stored in logs to this many characters.
    | Set to 0 for no limit.
    |
    */
    'max_url_length' => 500,

    /*
    |--------------------------------------------------------------------------
    | Max Payload Length
    |--------------------------------------------------------------------------
    | Truncate request payload stored in logs to this many characters.
    | Set to 0 for no limit.
    |
    */
    'max_payload_length' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Log Request Headers
    |--------------------------------------------------------------------------
    | Whether to capture and store request headers.
    |
    */
    'log_request_headers' => false,

    /*
    |--------------------------------------------------------------------------
    | Log Response Headers
    |--------------------------------------------------------------------------
    | Whether to capture and store response headers.
    |
    */
    'log_response_headers' => false,

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

    /*
    |--------------------------------------------------------------------------
    | Response Field Filtering
    |--------------------------------------------------------------------------
    | Keys to remove from response body before logging.
    | Supports nested dot notation: 'data.user.password'
    |
    */
    'exclude_response_keys' => [
        // 'data.password',
        // 'data.token',
        // 'data.secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Keys to Keep (allowlist)
    |--------------------------------------------------------------------------
    | Store only these keys of a JSON response body, to save database space.
    | Dot notation, with "*" for every key at one level: 'data.*.id'.
    | Empty array = store the whole body. Applied before exclude_response_keys
    | and before max_response_length. Non-JSON bodies are stored unchanged.
    |
    */
    'response_keys' => [
        // 'response',
        // 'uuid',
        // 'message',
        // 'code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Route Response Rules
    |--------------------------------------------------------------------------
    | Override response_keys for specific routes. The key is a route name or a
    | path pattern ("*" wildcard); the first match wins. The value is:
    |   - an array of keys to keep
    |   - ['*'] or [] to store the whole body
    |   - false to store no body at all
    |
    */
    'response_rules' => [
        // 'endpoint.getDataByCode' => ['response', 'uuid', 'message', 'code', 'data.status'],
        // 'api/v3/user*'           => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Keys to Keep (allowlist)
    |--------------------------------------------------------------------------
    | Same as response_keys, for the request payload. Empty array = store the
    | whole payload. Applied before hidden_fields masking and before
    | max_payload_length.
    |
    */
    'payload_keys' => [
        // 'site_id',
        // 'filter.*.name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Route Payload Rules
    |--------------------------------------------------------------------------
    | Same format as response_rules: route name or path pattern => array of
    | keys, ['*'] / [] for the whole payload, or false for no payload.
    |
    */
    'payload_rules' => [
        // 'endpoint.getDataByCode' => ['site_id', 'id_number'],
        // 'api/v3/password'        => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rules Managed in the Dashboard
    |--------------------------------------------------------------------------
    | When enabled, payload/response rules can be managed at /{prefix}/rules and
    | are stored in the api_logger_rules table (run the migrations first).
    | Database rules are matched before the *_rules above; *_keys remain the
    | fallback. Rules are cached for cache_ttl seconds (0 = no cache) and the
    | cache is cleared whenever a rule is saved or deleted.
    |
    */
    'rules' => [
        'database'  => env('APILOGGER_DB_RULES', false),
        'cache_ttl' => (int) env('APILOGGER_RULES_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Key Paths to Exclude (JSON Path)
    |--------------------------------------------------------------------------
    | More powerful exclusion using JSON path patterns.
    | Example: 'data.users[*].password' removes password from all users
    |
    */
    'exclude_response_patterns' => [
        // 'data.*.password',
        // 'data.*.token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes to Exclude from Logging
    |--------------------------------------------------------------------------
    | Routes that should not be logged (regex or simple string match).
    |
    */
    'exclude_routes' => [
        // 'health',
        // 'api/status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Only Log Specific Routes
    |--------------------------------------------------------------------------
    | If non-empty, only routes matching these patterns will be logged.
    | Empty means log all routes (except excluded ones above).
    |
    */
    'include_routes' => [
        // 'api/',
        // 'admin/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Exclusions
    |--------------------------------------------------------------------------
    | Additional payload keys to exclude beyond hidden_fields.
    |
    */
    'exclude_payload_keys' => [
        // 'token',
        // 'refresh_token',
    ],

];
