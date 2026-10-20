# Contributing

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Development setup](#development-setup)
- [Code style](#code-style)
- [Pull requests](#pull-requests)
- [Cursor Agent](#cursor-agent)
- [Git hooks (REQ-GIT-001)](#git-hooks-req-git-001)

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Development setup

1. Clone the repository.
2. Install git hooks once: `make setup-hooks` (REQ-MAKE-006 / REQ-GIT-001).
3. Start the dev container: `make up` then `make install`.
4. Run tests: `make test`, `make cs-check`, `make phpstan`, `make igor`.
5. Pre-release: `make release-check`.

## Code style

- PHP-CS-Fixer (PSR-12 + Symfony): `make cs-fix` / `make cs-check`.
- PHPStan (level 8+) including **`nowo-tech/phpstan-frankenphp`** classic + worker rulesets (`make phpstan`). Dev-only — never a runtime dependency of consuming apps (REQ-CS-005).
- PHPDoc and Markdown docs in **English**.
- Strict types in all PHP files (`declare(strict_types=1);`).
- Keep PHPUnit coverage at **100%** for `src/`.
- Do not copy proprietary host-application WhatsApp code into this repository.

## Pull requests

- Target the default branch.
- Ensure `make release-check` passes.
- Keep the changelog and docs updated when behaviour or config changes.

## Cursor Agent

Contributors using Cursor Agent on this repository should enable these user-level settings (REQ-IDE-006):

1. **Submit with Ctrl + Enter** (macOS: **⌘ + Enter**) — Cursor Settings → Agents / Chat. Enter inserts a newline; Ctrl/⌘+Enter submits.
2. **Queue follow-ups** in user `settings.json` (Linux `~/.config/Cursor/User/settings.json`, Windows `%APPDATA%\Cursor\User\settings.json`, macOS `~/Library/Application Support/Cursor/User/settings.json`):

```json
{
  "cursor.composer.queueMessageDefaultBehavior": "queue"
}
```

Do not use `"steer"` as the default: it interrupts a running agent turn.

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers, nor `Co-Authored-By: Claude …` / `noreply@anthropic.com` ones, to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` sets `core.hooksPath` to `.githooks`. Run it once per clone before your first commit.

If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.

Maintainers run `make check-no-cursor-coauthor` **after** creating a release commit and **before** `git push` (REQ-GIT-001): an earlier `release-check` run does not cover the release commit itself.
