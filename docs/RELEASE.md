# Release

## Checklist

1. `make setup-hooks` (once per clone).
2. Update [CHANGELOG.md](CHANGELOG.md) and [UPGRADING.md](UPGRADING.md).
3. `make release-check` (includes `check-no-cursor-coauthor`, open PRs, CS, Rector, PHPStan, coverage, demo smoke).
4. Commit release docs + code on `main`.
5. Tag `vX.Y.Z`, then run `make check-no-cursor-coauthor` again **after** the release commit and **before** `git push` (REQ-GIT-001: the earlier `release-check` does not cover the release commit).
6. Push branch + tag (`release.yml` / Sync Releases create the GitHub Release).

Do not add Cursor or Claude co-author trailers (REQ-GIT-001).
