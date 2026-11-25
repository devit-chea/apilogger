#!/usr/bin/env bash
# Release gdi/apilogger: check, test, bump composer.json, commit, tag, push.
#
#   release.sh <X.Y.Z|patch|minor|major>            dry run: checks + tests, prints the plan, changes nothing
#   release.sh <X.Y.Z|patch|minor|major> --push     does it: commit, annotated tag, push branch and tag
#   options: --skip-tests   (only when the core_api container can't be started; say so in the release)
#
# Pushing the tag starts .github/workflows/release.yml (validate, GitHub Release, Packagist update).
# Full ref names are used everywhere, in case a branch is named like a tag (v1.0.0 is both).
set -euo pipefail

die()  { echo "ERROR: $*" >&2; exit 1; }
info() { echo "==> $*"; }

BUMP="" PUSH=0 SKIP_TESTS=0
for arg in "$@"; do
    case "$arg" in
        --push)       PUSH=1 ;;
        --skip-tests) SKIP_TESTS=1 ;;
        -h|--help)    sed -n '2,9p' "$0"; exit 0 ;;
        -*)           die "unknown option $arg" ;;
        *)            [[ -z "$BUMP" ]] || die "only one version argument"; BUMP="$arg" ;;
    esac
done
[[ -n "$BUMP" ]] || die "usage: release.sh <X.Y.Z|patch|minor|major> [--push] [--skip-tests]"

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
REMOTE="${RELEASE_REMOTE:-origin}"

# --- where we are
# Not --short: with a tag of the same name it returns "heads/v2.0.0".
BRANCH_REF="$(git symbolic-ref --quiet HEAD)" || die "detached HEAD: check out the release branch first"
BRANCH="${BRANCH_REF#refs/heads/}"
DIRTY="$(git status --porcelain | grep -v ' \.DS_Store$' || true)"
[[ -z "$DIRTY" ]] || die "uncommitted changes (commit or stash them first):
$DIRTY"

info "Fetching $REMOTE"
git fetch --quiet --tags "$REMOTE"
if git rev-parse --quiet --verify "refs/remotes/$REMOTE/$BRANCH" >/dev/null; then
    BEHIND="$(git rev-list --count "HEAD..refs/remotes/$REMOTE/$BRANCH")"
    [[ "$BEHIND" -eq 0 ]] || die "$BRANCH is $BEHIND commit(s) behind $REMOTE/$BRANCH: pull first"
fi

# --- version
LATEST_TAG="$(git tag --list 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -1)"
LATEST="${LATEST_TAG#v}"
LATEST="${LATEST:-0.0.0}"
IFS=. read -r MA MI PA <<<"$LATEST"
case "$BUMP" in
    major) VERSION="$((MA + 1)).0.0" ;;
    minor) VERSION="$MA.$((MI + 1)).0" ;;
    patch) VERSION="$MA.$MI.$((PA + 1))" ;;
    *)     VERSION="${BUMP#v}" ;;
esac
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "version must look like 2.1.0 (got '$BUMP')"
TAG="v$VERSION"
git rev-parse --quiet --verify "refs/tags/$TAG" >/dev/null && die "tag $TAG already exists"
[[ "$(printf '%s\n%s\n' "$LATEST" "$VERSION" | sort -V | tail -1)" == "$VERSION" && "$LATEST" != "$VERSION" ]] \
    || die "$VERSION is not newer than the latest tag ${LATEST_TAG:-none}"

# Branches are per major version (2.x; older: v1.0.0); warn when releasing another major from one.
if [[ "$BRANCH" =~ ^v?([0-9]+)\. && "${BASH_REMATCH[1]}" != "${VERSION%%.*}" ]]; then
    echo "WARNING: releasing $TAG from branch $BRANCH (major ${BASH_REMATCH[1]})." >&2
fi

# --- changes since the last tag
RANGE="HEAD"
[[ -n "$LATEST_TAG" ]] && RANGE="refs/tags/$LATEST_TAG..HEAD"
NOTES="$(git log --no-merges --pretty='- %s' "$RANGE" | grep -v '^- chore: release v' || true)"
[[ -n "$NOTES" ]] || die "no commits since ${LATEST_TAG:-the start}: nothing to release"

# --- tests
if [[ $SKIP_TESTS -eq 1 ]]; then
    echo "WARNING: tests skipped (--skip-tests)" >&2
else
    info "Running checks (.claude/skills/test)"
    set +e
    "$ROOT/.claude/skills/test/scripts/run.sh"
    STATUS=$?
    set -e
    [[ $STATUS -eq 2 ]] && die "checks could not run (is the core_api container up?). Start it, or pass --skip-tests deliberately."
    [[ $STATUS -eq 0 ]] || die "checks failed: not releasing"
fi

HAS_VERSION_FIELD=0
grep -qE '^\s*"version"\s*:' composer.json && HAS_VERSION_FIELD=1

echo
info "Plan"
echo "  branch:   $BRANCH  ->  $REMOTE (refs/heads/$BRANCH)"
echo "  version:  ${LATEST_TAG:-none}  ->  $TAG"
[[ $HAS_VERSION_FIELD -eq 1 ]] && echo "  commit:   composer.json \"version\": \"$VERSION\"  (chore: release $TAG)"
echo "  tag:      $TAG (annotated) on that commit, pushed as refs/tags/$TAG"
echo "  then:     GitHub Actions 'Release' validates the tag, creates the GitHub Release and updates Packagist"
echo
echo "Release notes:"
echo "$NOTES"
echo

if [[ $PUSH -eq 0 ]]; then
    info "Dry run: nothing changed. Run again with --push to release."
    exit 0
fi

# --- release
if [[ $HAS_VERSION_FIELD -eq 1 ]]; then
    perl -0pi -e 's/("version"\s*:\s*")[^"]*(")/${1}'"$VERSION"'${2}/' composer.json
    grep -q "\"version\": \"$VERSION\"" composer.json || die "could not update the version in composer.json"
    git add composer.json
    git commit --quiet -m "chore: release $TAG"
    info "Committed composer.json version $VERSION"
fi

git tag -a "$TAG" -m "$TAG" -m "$NOTES"
info "Tagged $TAG"

if ! git push "$REMOTE" "refs/heads/$BRANCH:refs/heads/$BRANCH"; then
    die "pushing $BRANCH failed. The commit and tag are local only; fix the problem and run:
  git push $REMOTE refs/heads/$BRANCH:refs/heads/$BRANCH && git push $REMOTE refs/tags/$TAG:refs/tags/$TAG
or undo with: git tag -d $TAG && git reset --hard HEAD~$HAS_VERSION_FIELD"
fi
if ! git push "$REMOTE" "refs/tags/$TAG:refs/tags/$TAG"; then
    die "branch pushed, but pushing tag $TAG failed. Retry: git push $REMOTE refs/tags/$TAG:refs/tags/$TAG"
fi

URL="$(git remote get-url "$REMOTE" | sed -E 's#^git@github.com:#https://github.com/#; s#\.git$##')"
info "Released $TAG"
echo "  Workflow:  $URL/actions/workflows/release.yml"
echo "  Release:   $URL/releases/tag/$TAG"
echo "  Packagist: https://packagist.org/packages/gdi/apilogger"
echo "  Then in core-api-v3: composer update gdi/apilogger"
