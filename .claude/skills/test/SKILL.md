---
name: test
description: Run the gdi/apilogger checks against a real Laravel host app (core-api-v3 in its Docker container) using the package's working tree. Use after changing anything in src/, config/, routes/, resources/ or database/, when asked to test or verify the package, or to add a new check.
---

# Testing gdi/apilogger

The package has no PHPUnit suite and its own `vendor/` lacks the Laravel framework, so checks run inside a host app. The default host is core-api-v3's `core_api` container (app at `/app`). The local Homebrew PHP may not start, so don't rely on running `php` on the Mac.

## Run

```bash
.claude/skills/test/scripts/run.sh            # all checks
.claude/skills/test/scripts/run.sh rules      # only check files whose name contains "rules"
```

Other host: `APILOGGER_TEST_CONTAINER=<name> APILOGGER_TEST_APP_DIR=<path> .claude/skills/test/scripts/run.sh`.

If the container isn't running, the script exits with code 2. Ask the user to start core-api-v3 (`docker compose -f docker-compose-local.yml up -d` in that project) instead of guessing another way to run PHP.

What the runner does:
- copies `config database resources routes src` of this working tree to `/tmp/apilogger-test` in the container (uncommitted changes are tested), plus `scripts/bootstrap.php` and `scripts/checks/`
- runs each `checks/*.php` as its own PHP process, with in-memory SQLite, array cache/session, sync queue and the `null` log channel, so the app's real database, cache and log files are not touched
- loads the package copy through an autoloader prepended before Composer's: the app's optimized classmap would otherwise load `vendor/gdi/apilogger`. `bootstrap.php` aborts if the provider still comes from `vendor/`
- deletes the copy afterwards; exits 1 if any check file has a FAIL

Report the PASS/FAIL lines to the user. A FAIL is either a package bug or a wrong expectation in the check: read the `->` value before changing code, and say which one it was.

## Check files

| File | Covers |
|---|---|
| `checks/filtering.php` | `response_keys`/`response_rules`, `payload_keys`/`payload_rules`, `*` wildcard, false/`[*]`/`[]` rules, masking, exclusions applied after the allowlist, filtering before truncation, non-JSON bodies, unescaped Unicode |
| `checks/rules_dashboard.php` | `api_logger_rules` migration, Rules pages (index/create/edit/update/delete), Import from config and `apilogger:import-rules`, validation, cache flush on save, rules applied by the host's bound logger, fallback when the table is missing, `/{id}` route still working |

## Adding a check

Create `scripts/checks/<topic>.php`:

```php
<?php
$overrides = ['apilogger.some_key' => 'value'];   // config for this check only; set every key the check depends on
require __DIR__ . '/../bootstrap.php';

migrate_package();                                 // only if the check needs the package tables
$response = send('GET', '/apilogger');             // full HTTP request through the app kernel
$request  = request_for('api/v3/x', 'route.name', ['a' => 1]);  // request already matched to a named route
check('what is being verified', $condition, (string) $actualValue);

finish();                                          // required: prints the summary and writes the status run.sh reads
```

Helpers come from `scripts/bootstrap.php`: `$app`, `$kernel`, `check()`, `send()`, `request_for()`, `migrate_package()`, `finish()`. Dashboard routes run with only `web` middleware, and CSRF is skipped because `APP_ENV=testing`.

Keep checks independent of the host's own `config/apilogger.php`: set the keys you rely on in `$overrides`. Host-specific behaviour (e.g. core-api-v3's `GatewayDatabaseLogger` adding `uuid`) should be checked only when present, as `rules_dashboard.php` does.
