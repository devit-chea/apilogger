# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`gdi/apilogger` is a Laravel package (not an application) that logs API requests/responses and serves a Blade dashboard to browse them. Namespace `GDI\ApiLogger\` → `src/`. Supports PHP ^8.1 and Laravel 10/11/12 (`illuminate/*` only; there is no `laravel/framework` dependency).

There is no PHPUnit suite, linter, or build step, and this repo's `vendor/` doesn't include the Laravel framework. Checks run inside a host app's container instead (core-api-v3's `core_api` by default). They test the working tree, uncommitted changes included, and don't touch the host's database or files:

```bash
.claude/skills/test/scripts/run.sh          # all checks
.claude/skills/test/scripts/run.sh rules    # check files whose name contains "rules"
```

Releases: `.claude/skills/release/scripts/release.sh <patch|minor|major|X.Y.Z>` does a dry run (checks, tests, plan). Add `--push` to bump `composer.json` `"version"`, commit, tag and push. `.github/workflows/release.yml` then creates the GitHub Release and updates Packagist. Host apps install the package from Packagist. Branch names match tag names (`v2.0.0` is both), so always use full refs (`refs/heads/…`, `refs/tags/…`). See `.claude/skills/release/SKILL.md`.

See `.claude/skills/test/SKILL.md` for how it works and how to add a check under `.claude/skills/test/scripts/checks/`. The dashboard lives at `/apilogger`.

## Architecture

**Request flow:** `ApiLoggerMiddleware` (alias `apilogger`) turns on `DB::enableQueryLog()`, runs the request, then calls `$logger->buildLogData()`. It then calls `save()` directly, or dispatches `Jobs\WriteApiLog` when `apilogger.queue.enabled` is set. Any logging exception is caught and sent to `logger()->error()`. Logging must never break the host request.

**Driver abstraction:** `ApiLoggerServiceProvider::register()` binds `Contracts\ApiLoggerInterface` as a singleton based on `config('apilogger.driver')`:
- `'file'` → `Drivers\FileLogger`. Stores all entries as one pretty-printed JSON array in `storage/<log_file>`. Every save reads and rewrites the whole file. The array is pruned to `max_entries`, and pagination happens in memory.
- `'db'` → `Drivers\DatabaseLogger`. Uses two tables: `api_loggers` (indexed metadata, model `ApiLogger`) and `api_logger_responses` (large blobs, model `ApiLoggerResponse`, FK cascade). `find()` merges the response row back into one flat array.
- Any other FQCN is instantiated directly (custom drivers). Anything unrecognized throws `InvalidApiLogDriverException`.

The interface has four methods: `save`, `all` (returns a paginator; the views expect `LengthAwarePaginator`), `find`, and `clear`. The middleware calls `buildLogData()`, which is **not** on the interface. It comes from `Drivers\AbstractLogger`, so in practice every driver must extend `AbstractLogger`.

**`AbstractLogger::buildLogData()`** holds all capture and sanitization logic. It runs route include/exclude checks (an empty array return means "skip"), masks hidden or payload fields (dot notation), truncates payload/response/URL, removes response keys and wildcard patterns, parses controller@action, and guesses model names from `FROM <table>` in the query log. The log entry's public ID is the `id` key (`uniqid('log_')`). The DB driver stores it as `log_id`, and the dashboard routes look it up by that key, not by the auto-increment PK.

**Storage rules:** `AbstractLogger::keysFor()` decides which payload/response keys are stored. It asks `Rules\RuleRepository` (singleton) for rules in match order: enabled `api_logger_rules` rows (`Models\ApiLoggerRule`, only when `apilogger.rules.database` is on, cached under `apilogger.rules` and flushed by model events), then `{type}_rules` from config, then the `{type}_keys` fallback. Rule lookup must never throw, and `extractModels()` runs before it so the rules query isn't logged as a model.

**Dashboard:** `routes/web.php` reads `route.prefix` and `route.middleware` from config and registers the named routes `apilogger.index`, `apilogger.show`, `apilogger.response` and `apilogger.clear` (POST). The `rules/*` routes (`ApiLoggerRuleController`) must stay registered before `/{id}`, or "rules" is taken as a log id. `ApiLoggerController` resolves the same interface binding, so the dashboard reads from whichever driver is configured. Views are namespaced `apilogger::` and are self-contained, with no external CSS or JS (`layouts/app.blade.php`).

**Service provider:** it auto-loads views, migrations and routes. Under console it registers the publish tags `apilogger-config`, `apilogger-views` and `apilogger-migrations`, plus the `apilogger:clear` command (`Console\ClearLogsCommand`).

## Gotchas in current code

- `composer.json` declares a facade alias `GDI\ApiLogger\Facades\ApiLogger`, but no `src/Facades` directory exists.
- `Jobs\WriteApiLog::handle()` type-hints `GDI\ApiLogger\Contracts\ApiLoggerInterface` without a leading `\`. Inside the `GDI\ApiLogger\Jobs` namespace that resolves to the wrong class, so the queued path is broken until it is fixed or imported.
- `DatabaseLogger::clear()` calls `ApiLogger::truncate()` on a table that `api_logger_responses` references by FK. Truncate does not trigger `ON DELETE CASCADE`, and MySQL refuses to truncate a referenced table.
- Route include/exclude entries are used both as substrings and as raw regex bodies (`'/' . $route . '/'`), so `/` or other regex metacharacters in a config value can cause `preg_match` warnings.
- When you add a new config key, add it to `config/apilogger.php` and to the README configuration tables. Env overrides use the `APILOGGER_` prefix.
