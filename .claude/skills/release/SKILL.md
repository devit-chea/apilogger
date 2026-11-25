---
name: release
description: Release a new version of gdi/apilogger (bump composer.json, tag, push to GitHub; GitHub Actions then creates the GitHub Release and updates Packagist). Use when asked to release, publish, push, tag or ship the package, or to bump its version.
---

# Releasing gdi/apilogger

Host apps (core-api-v3) install the package from Packagist, which reads version tags from `github.com/devit-chea/apilogger`. A release is a `vX.Y.Z` tag on GitHub.

## Steps

1. **Pick the version** from the commits since the last tag (`git log --oneline $(git tag --sort=-v:refname | head -1 | sed 's#^#refs/tags/#')..HEAD`): `patch` for fixes, `minor` for new options or features that keep old config working, `major` for breaking changes. If unsure, ask the user.

2. **Dry run**: checks, runs the test skill, and prints the plan and release notes. Nothing is changed:
   ```bash
   .claude/skills/release/scripts/release.sh minor        # or patch / major / 2.1.0
   ```
   If it stops, fix the cause instead of working around it. Uncommitted changes: ask the user whether to commit them first. Behind the remote: pull. Checks fail: report it and don't release. Checks can't run (exit code 2, core_api container down): ask the user to start it. Use `--skip-tests` only if the user explicitly agrees, and say so in your report.

3. **Ask the user to confirm**: show the version, branch and release notes from the dry run. Pushing a tag publishes the version to every app that runs `composer update`, and a tag on Packagist can't really be taken back. Don't push without a yes for this specific version.

4. **Release**:
   ```bash
   .claude/skills/release/scripts/release.sh minor --push
   ```
   This commits `composer.json` with the new `"version"` (`chore: release vX.Y.Z`), creates an annotated tag whose message holds the release notes, then pushes the branch and the tag.

5. **Report** the links the script prints (Actions run, GitHub Release, Packagist), and remind the user to run `composer update gdi/apilogger` in core-api-v3. `gh` is not installed, so you can't watch the workflow from here: tell the user to check the Actions link.

## What the workflow does

`.github/workflows/release.yml` runs on the pushed tag. It validates `composer.json`, checks that the tag equals the `"version"` in `composer.json`, runs `php -l` on PHP 8.1 (the lowest supported version), creates the GitHub Release from the tag message, and calls the Packagist update API when the repository secrets `PACKAGIST_USERNAME` and `PACKAGIST_API_TOKEN` are set.

## Gotchas

- Branches are per major version (`v1.0.0`, `v2.0.0`) and share names with tags. Plain `git push origin v2.0.0` is ambiguous, so the script always uses `refs/heads/...` and `refs/tags/...`. Do the same if you push by hand.
- `composer.json` has a fixed `"version"` field. It must match the tag, or Packagist reports a mismatch. The script bumps it.
- If a push fails half way, the script prints the exact commands to retry or undo. Never delete or move a tag that is already on GitHub without the user asking.
