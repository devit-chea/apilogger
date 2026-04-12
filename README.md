# GDI API Logger

A self-maintained Laravel package for logging, inspecting, and debugging API requests.
Supports both **file** and **database** drivers, a built-in dashboard, and is fully extensible with custom drivers.

---

## Features

- Logs: HTTP method, URL, status code, duration (ms), request payload, response body, controller, action method, Eloquent models retrieved, IP, and authenticated user
- Two storage drivers out of the box: **file** and **db**
- Pluggable custom driver interface
- Dashboard UI at `/apilogger` with filtering, pagination, and detail view
- Hidden fields (passwords, tokens, etc.) automatically masked
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

| Key | Default | Description |
|---|---|---|
| `driver` | `file` | Storage driver: `file`, `db`, or a custom class name |
| `log_file` | `logs/apilogger.log` | File path (relative to `storage/`) for the file driver |
| `max_entries` | `1000` | Max entries kept by file driver (0 = unlimited) |
| `hidden_fields` | `[password, token, ...]` | Request fields replaced with `***` |
| `log_response` | `true` | Whether to capture response body |
| `max_response_length` | `5000` | Characters to store per response (0 = unlimited) |
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
- **May** extend `GDI\ApiLogger\Drivers\AbstractLogger` to inherit `buildLogData()` and `filterHidden()` helpers

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
│       └── 2024_01_01_000000_create_api_logs_table.php
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
    ├── Http/
    │   ├── Controllers/
    │   │   └── ApiLoggerController.php
    │   └── Middleware/
    │       └── ApiLoggersMiddleware.php
    ├── Models/
    │   └── ApiLogger.php
    └── Providers/
        └── ApiLoggerServiceProvider.php
```

---

## License

MIT
