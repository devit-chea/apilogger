# API Logger

A self-maintained Laravel package for logging, inspecting, and debugging API requests.
Supports both **file** and **database** drivers, a built-in dashboard, and is fully extensible with custom drivers.

---

## Features

- Logs: HTTP method, URL, status code, duration (ms), request payload, response body, controller, action method, Eloquent models retrieved, IP, and authenticated user
- Two storage drivers out of the box: **file** and **db**
- Pluggable custom driver interface
- Dashboard UI at `/apilogger` with filtering, pagination, and detail view
- Hidden fields (passwords, tokens, etc.) automatically masked
- **Response filtering** - Remove sensitive keys from logged responses
- **Route filtering** - Exclude or include specific routes from logging
- **Performance optimized** - Database driver splits large data into separate table
- Artisan command to clear logs
- Zero external JS/CSS dependencies — dashboard is self-contained

---

## Installation

### 1. Place the package in your project

For an internal package, add it to your project's `packages/` directory and register it in `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/apilogger"
    }
],
"require": {
    "gdi/apilogger": "*"
}
```

Then run:

```bash
composer require gdi/apilogger
```

### 2. Publish the config

```bash
php artisan vendor:publish --tag=apilogger-config
```

This creates `config/apilogger.php`.

### 3. (Optional) Publish views

Only needed if you want to customise the dashboard UI:

```bash
php artisan vendor:publish --tag=apilogger-views
```

### 4. (DB driver only) Run the migration

```bash
php artisan vendor:publish --tag=apilogger-migrations
php artisan migrate
```

---

## Configuration

`config/apilogger.php`:

### Storage Settings

| Key | Default | Description |
|---|---|---|
| `driver` | `file` | Storage driver: `file`, `db`, or a custom class name |
| `log_file` | `logs/apilogger.log` | File path (relative to `storage/`) for the file driver |
| `max_entries` | `1000` | Max entries kept by file driver (0 = unlimited) |

### Queue Settings

| Key | Default | Description |
|---|---|---|
| `queue.enabled` | `false` | Enable async logging via queue |
| `queue.connection` | `default` | Queue connection to use |
| `queue.queue` | `default` | Queue name to push jobs to |

You can also set these via `.env`:

```env
APILOGGER_DRIVER=db
APILOGGER_QUEUE_ENABLED=true
APILOGGER_QUEUE_CONNECTION=database
APILOGGER_QUEUE_NAME=logs
```

### Data Capture Settings

| Key | Default | Description |
|---|---|---|
| `log_response` | `true` | Whether to capture response body |
| `log_request_headers` | `false` | Whether to capture request headers |
| `log_response_headers` | `false` | Whether to capture response headers |

### Data Truncation Settings

| Key | Default | Description |
|---|---|---|
| `max_response_length` | `5000` | Max characters per response (0 = unlimited) |
| `max_url_length` | `500` | Max characters per URL (0 = unlimited) |
| `max_payload_length` | `5000` | Max characters per payload (0 = unlimited) |

### Security Settings

| Key | Default | Description |
|---|---|---|
| `hidden_fields` | `[password, password_confirmation, secret, token, api_key, credit_card]` | Request fields replaced with `***` |
| `exclude_payload_keys` | `[]` | Additional payload keys to mask |
| `exclude_response_keys` | `[]` | Response keys to remove (supports nested: `data.user.password`) |
| `exclude_response_patterns` | `[]` | Response patterns with wildcards (e.g., `data.*.password`) |

### Route Filtering

| Key | Default | Description |
|---|---|---|
| `exclude_routes` | `[]` | Routes to skip logging (string or regex) |
| `include_routes` | `[]` | Only log these routes (empty = log all) |

### Dashboard Settings

| Key | Default | Description |
|---|---|---|
| `route.prefix` | `apilogger` | Dashboard URL prefix |
| `route.middleware` | `['web']` | Middleware applied to dashboard routes |
| `per_page` | `20` | Entries per page on the dashboard |

You can also set these via `.env`:

```env
APILOGGER_DRIVER=db
APILOGGER_LOG_RESPONSE=true
```

---

## Usage

### Add middleware to routes

Apply the `apilogger` middleware to any route or route group you want to log:

```php
// Single route
Route::middleware('apilogger')->post('/api/orders', [OrderController::class, 'store']);

// Route group
Route::middleware(['auth:sanctum', 'apilogger'])->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

### Dashboard

Visit `http://yourdomain.com/apilogger` to view the log dashboard.

You can filter by HTTP method, status code, and URL substring.

### Clear logs via Artisan

```bash
php artisan apilogger:clear
```

### Using Queues for Async Logging

For high-traffic applications, you can enable async logging to avoid blocking API responses:

```php
// config/apilogger.php
'queue' => [
    'enabled' => true,
    'connection' => 'database',  // redis, sqs, etc.
    'queue' => 'logs',
],
```

Or via `.env`:

```env
APILOGGER_QUEUE_ENABLED=true
APILOGGER_QUEUE_CONNECTION=database
APILOGGER_QUEUE_NAME=logs
```

Make sure your queue worker is running:

```bash
php artisan queue:work --queue=logs
```

---

## Advanced Configuration

### Storing Only the Important Response Data

To save database space, keep only the keys you need from a JSON response. Dot notation is supported, and `*` matches every key at one level (`data.*.id` keeps the `id` of every item):

```php
// Applies to every logged route. Empty array = store the whole body.
'response_keys' => [
    'response',
    'uuid',
    'message',
    'code',
],
```

Override it per route. The key is a route name or a path pattern (`*` wildcard), and the first match wins:

```php
'response_rules' => [
    'endpoint.getDataByCode' => ['response', 'uuid', 'message', 'code', 'data.status'],
    'api/v3/user*'           => false,   // store no response body
    'api/v3/debug/*'         => ['*'],   // store the whole body
],
```

Order of processing: keep `response_keys` / rule keys → remove `exclude_response_keys` and `exclude_response_patterns` → cut to `max_response_length`. Non-JSON bodies (e.g. HTML error pages) are stored unchanged, then cut. Bodies are stored with unescaped Unicode, so Khmer text takes about half the space of `\uXXXX` escapes.

### Storing Only the Important Payload Data

The request payload has the same options, `payload_keys` and `payload_rules`, with the same format:

```php
'payload_keys' => [],   // empty = store the whole payload

'payload_rules' => [
    'endpoint.getDataByCode' => ['site_id', 'id_number'],
    'api/v3/password'        => false,   // store no payload
],
```

Order of processing: keep `payload_keys` / rule keys → mask `hidden_fields` and `exclude_payload_keys` with `***` → cut to `max_payload_length`.

### Managing Rules in the Dashboard

Payload and response rules can also be stored in the database and managed at `/apilogger/rules` (protected by the dashboard's `route.middleware`).

```env
APILOGGER_DB_RULES=true
APILOGGER_RULES_CACHE_TTL=300   # seconds, 0 = no cache
```

```bash
php artisan migrate                    # creates api_logger_rules
php artisan apilogger:import-rules     # optional: copy the config rules into the table
```

The **Import from config** button on the Rules page does the same as the command. Rules with the same type and pattern are skipped, so it is safe to run twice. Global `*_keys` lists are imported as a `*` rule with priority 1000.

Each rule has a type (payload or response), a route name or path pattern, an action (keep these keys / store everything / store nothing), a priority (lower runs first) and an enabled switch. Matching order: enabled database rules by priority → `*_rules` from config → `*_keys` from config. Rules are cached; saving or deleting a rule clears the cache, and each process re-reads it within 10 seconds.

### Filtering Response Data

Remove sensitive keys from logged responses:

```php
'exclude_response_keys' => [
    'data.password',
    'data.token',
    'data.secret',
    'user.card_number',  // Nested keys supported
],
```

Use wildcard patterns for arrays:

```php
'exclude_response_patterns' => [
    'data.users[*].password',  // Removes password from all users
    'data.items[*].secret',
],
```

### Excluding Routes

Skip logging for health checks or status endpoints:

```php
'exclude_routes' => [
    'health',
    'api/status',
    'webhook/test',  // Exact match or regex
],
```

Only log specific routes:

```php
'include_routes' => [
    'api/',
    'admin/',
],
```

### Masking Nested Payload Fields

Mask sensitive data in nested request payloads:

```php
'hidden_fields' => [
    'password',
    'token',
],

'exclude_payload_keys' => [
    'user.password',
    'card.cvv',
],
```

---

## Database Driver Optimization

The database driver uses a two-table schema for better performance:

- **`api_loggers`** - Indexable fields (method, URL, status_code, user_id, logged_at)
- **`api_logger_responses`** - Large data (response, payload, headers)

This separation allows:
- Faster queries on the main table
- Lazy loading of response data only when needed
- Efficient indexing on frequently filtered fields

---

## Securing the Dashboard

In `config/apilogger.php`, add authentication middleware:

```php
'route' => [
    'prefix'     => 'apilogger',
    'middleware' => ['web', 'auth'],   // or ['web', 'auth', 'can:view-logs']
],
```

---

## Custom Driver

You can implement your own storage backend (e.g. Redis, MongoDB, S3):

### Step 1 — Create your driver class

```php
<?php

namespace App\ApiLogger;

use GDI\ApiLogger\Drivers\AbstractLogger;

class RedisLogger extends AbstractLogger
{
    public function save(array $data): void
    {
        // push $data to Redis
    }

    public function all(int $perPage = 20, array $filters = []): mixed
    {
        // return paginated results
    }

    public function find(mixed $id): ?array
    {
        // return a single entry or null
    }

    public function clear(): void
    {
        // delete all entries
    }
}
```

Your class:
- **Must** implement `GDI\ApiLogger\Contracts\ApiLoggerInterface`
- **May** extend `GDI\ApiLogger\Drivers\AbstractLogger` to inherit `buildLogData()` and filtering helpers

### Step 2 — Register your driver

In `config/apilogger.php`:

```php
'driver' => \App\ApiLogger\RedisLogger::class,
```

---

## File Structure

```
packages/apilogger/
├── composer.json
├── config/
│   └── apilogger.php
├── database/
│   └── migrations/
│       ├── 2026_01_01_000000_create_api_loggers_table.php
│       └── 2026_01_02_000000_create_api_logger_responses_table.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── index.blade.php
│       └── show.blade.php
├── routes/
│   └── web.php
└── src/
    ├── Console/
    │   └── ClearLogsCommand.php
    ├── Contracts/
    │   └── ApiLoggerInterface.php
    ├── Drivers/
    │   ├── AbstractLogger.php
    │   ├── DatabaseLogger.php
    │   └── FileLogger.php
    ├── Exceptions/
    │   └── InvalidApiLogDriverException.php
    ├── Http/
    │   ├── Controllers/
    │   │   └── ApiLoggerController.php
    │   └── Middleware/
    │       └── ApiLoggerMiddleware.php
    ├── Jobs/
    │   └── WriteApiLog.php
    ├── Models/
    │   ├── ApiLogger.php
    │   └── ApiLoggerResponse.php
    └── Providers/
        └── ApiLoggerServiceProvider.php
```

---

## License

MIT
