#!/usr/bin/env bash
# Run the apilogger checks inside a host Laravel app's container (core-api-v3 by default).
#
#   .claude/skills/test/scripts/run.sh            # all checks
#   .claude/skills/test/scripts/run.sh rules      # only checks whose file name contains "rules"
#
# The working tree of this package is copied to /tmp in the container and loaded in front of the
# app's vendor/gdi/apilogger. The app runs on in-memory SQLite with array cache/session, so its
# real database, cache and files are not touched. The copy is removed afterwards.
set -euo pipefail

SKILL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PKG_DIR="$(cd "$SKILL_DIR/../../.." && pwd)"
CONTAINER="${APILOGGER_TEST_CONTAINER:-core_api}"
APP_DIR="${APILOGGER_TEST_APP_DIR:-/app}"
TMP=/tmp/apilogger-test
FILTER="${1:-}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
    echo "Container '$CONTAINER' is not running. Start the host app (e.g. core-api-v3's docker compose)" >&2
    echo "or set APILOGGER_TEST_CONTAINER / APILOGGER_TEST_APP_DIR." >&2
    exit 2
fi

cleanup() { docker exec "$CONTAINER" rm -rf "$TMP" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker exec "$CONTAINER" sh -c "rm -rf $TMP && mkdir -p $TMP/pkg $TMP/checks"
# --no-xattrs / --no-mac-metadata: keep macOS attributes out of the archive (GNU tar warns on them)
tar --no-xattrs --no-mac-metadata --no-fflags -C "$PKG_DIR" -cf - config database resources routes src \
    | docker exec -i "$CONTAINER" tar -xf - -C "$TMP/pkg"
tar --no-xattrs --no-mac-metadata --no-fflags -C "$SKILL_DIR/scripts" -cf - bootstrap.php checks \
    | docker exec -i "$CONTAINER" tar -xf - -C "$TMP"

failed=0
ran=0
for check in "$SKILL_DIR"/scripts/checks/*.php; do
    name="$(basename "$check")"
    [[ -n "$FILTER" && "$name" != *"$FILTER"* ]] && continue
    ran=$((ran + 1))
    echo "== $name"
    if ! docker exec \
        -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
        -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
        -e APILOGGER_TEST_APP_DIR="$APP_DIR" \
        -w "$APP_DIR" "$CONTAINER" php "$TMP/checks/$name" 2>&1 | grep -v 'Cannot load Zend OPcache'; then
        :
    fi
    # grep hides the exit code; read the result line the check printed instead
    status=$(docker exec "$CONTAINER" cat "$TMP/checks/.status-$name" 2>/dev/null || echo "crashed")
    if [[ "$status" != "ok" ]]; then
        failed=$((failed + 1))
        [[ "$status" == "crashed" ]] && echo "FAIL $name did not finish (see error above)"
    fi
    echo
done

if [[ $ran -eq 0 ]]; then
    echo "No check matches '$FILTER'." >&2
    exit 2
fi
if [[ $failed -gt 0 ]]; then
    echo "$failed of $ran check file(s) FAILED"
    exit 1
fi
echo "All $ran check file(s) passed"
