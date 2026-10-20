# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of the files under `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **WhatsappBusinessBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (FrankenPHP classic + worker rulesets) enforce the contracts in CI.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests, and static analysis are the mechanical proof alongside this document.

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
  - [Goal](#goal)
  - [In scope](#in-scope)
  - [Explicit non-goals](#explicit-non-goals)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [Relationship to Engram](#relationship-to-engram)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## User stories

| ID | Story | Integrator docs |
| --- | --- | --- |
| US-01 | As a Symfony app, I send a WhatsApp template through a named profile with HTTP timeouts and masked logging. | [USAGE — Templates and media](USAGE.md#templates-and-media), [CONFIGURATION — Named profiles](CONFIGURATION.md#named-profiles) |
| US-02 | As a Symfony app, I accept Meta webhooks only when `X-Hub-Signature-256` validates against the app secret. | [CONFIGURATION — Webhooks](CONFIGURATION.md#webhooks), [USAGE — Events](USAGE.md#events) |
| US-03 | As a Symfony app, I send media, reactions, locations or host-built Graph JSON (`sendRaw()`). | [USAGE — sendRaw](USAGE.md#sendraw-host-graph-json) |
| US-04 | As an operator, I queue sends through Messenger and use the console commands. | [USAGE — Messenger](USAGE.md#messenger), [USAGE — CLI](USAGE.md#cli) |
| US-05 | As a developer, I use the mock transport in demos/tests without leaking recordings between worker requests. | [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md), [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) |

## Bundle functional scope

### Goal

A reusable, worker-safe client for the public WhatsApp Cloud API (Graph): outbound messages, media, templates and Meta webhooks, configured as named profiles under `nowo_whatsapp_business`.

### In scope

- Named profiles with HTTPS-only Graph base URIs and HTTP timeouts.
- Typed outbound messages, `sendRaw()`, media upload/download (Meta-only download hosts), templates listing.
- Webhook verification (verify token + HMAC) and events for inbound messages and statuses.
- Messenger `SendOutgoingMessage`, console `whatsapp:send-text` / `whatsapp:templates`.
- Mock transport for demos/tests.

### Explicit non-goals

- Back-office CRUD, Twig message composers or a template CMS.
- Persisting messages, contacts or conversations (host-owned).
- Wrapping unofficial third-party WhatsApp libraries as the public API.
- The demo under `demo/` is illustrative, not part of the Packagist API.

## Validating the functional spec

- **`make qa`** (Docker): coding standard, PHPStan, PHPUnit.
- **`make test-coverage`**: PHP line coverage (100%).
- **`make release-check`**: full pre-release gate (includes `check-no-cursor-coauthor`, `check-open-prs`, demo healthchecks).
- Behavior changes require tests under `tests/` and an update of [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md).

## Requirement identifiers (`REQ-*`)

| ID | Location | Meaning |
| --- | --- | --- |
| REQ-GIT-001 | `.githooks/commit-msg`, `make check-no-cursor-coauthor`, CI `git-hygiene` | No AI co-author trailers in history |
| REQ-CS-008 | Root `Makefile` (`igor`) | Igor worker-state audit |
| REQ-TEST-011 | Root `Makefile` (`demo-smoke`) | Boot demos and assert HTTP 200 |
| REQ-REL-003 | Root `Makefile` (`check-open-prs`) | Fail on unresolved open PRs before release |
| REQ-MAKE-008 / REQ-MAKE-009 | Root and demo `Makefile` (`update-deps`) | Dependency updates; optional monorepo helper |
| REQ-MAKE-010 | Root and demo `Makefile` | Prefer Compose V2, fall back to V1 |
| REQ-RUNTIME-001 | `Http/GraphApiClient.php`, profile timeouts | Explicit HTTP timeouts |

When scripted behavior changes, update the comment next to the existing `REQ-*` or add a new ID and cite it from PRs/issues.

## Suggested workflow for contributors

1. **Clarify** the behavior in an issue, the baseline spec, or a new `specs/00N-*` folder.
2. **Implement with tests** (`make test`, `make phpstan`).
3. **Anchor scripts and demos** with `REQ-*` comments when dev UX changes.
4. **Update Spec Kit artifacts** — `spec.md` and `code-inventory.md` when `src/` changes (see the maintainer checklist in [SPEC-KIT.md](SPEC-KIT.md)).
5. **Ship integrator docs** (`USAGE.md`, `CONFIGURATION.md`, `CHANGELOG.md`, `UPGRADING.md`); validate with `make release-check`.

## Relationship to Engram

[ENGRAM.md](ENGRAM.md) documents the org-wide checklist plane (Engram MCP memory, documentation hygiene). It does **not** replace this document: the functional contract (user stories, scope, validation, `REQ-*` anchors) lives here and in `specs/001-baseline/`.

## GitHub Spec Kit (summary)

| Artifact | Path |
| --- | --- |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100% of `src/`) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor skills | `.cursor/skills/speckit-*` |

Re-initialize with `specify init --here --force --integration cursor-agent --script sh`. **Full manual:** [SPEC-KIT.md](SPEC-KIT.md).

## See also

- [SPEC-KIT.md](SPEC-KIT.md) · [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md)
- [USAGE.md](USAGE.md) · [CONFIGURATION.md](CONFIGURATION.md) · [CONTRIBUTING.md](CONTRIBUTING.md) · [RELEASE.md](RELEASE.md)
- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) · [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)
