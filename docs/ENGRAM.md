# Engram

Short facts for AI assistants and maintainers.

- Package: `nowo-tech/whatsapp-business-bundle`
- Bundle class: `Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle`
- Config alias: `nowo_whatsapp_business`
- Main services: `WhatsappClientInterface`, `WhatsappClientRegistryInterface`
- Profiles: `default_profile` + `profiles.*` (phone_number_id, access_token, timeouts, webhook secrets)
- Transports: `cloud` (Graph API via Symfony HttpClient) and `mock` (`ResetInterface` + per-request clear + bounded buffer)
- Webhooks: `/whatsapp/webhook` + `/whatsapp/webhook/{profile}`
- FrankenPHP: worker-safe with or without kernel reset; HTTP timeouts on every Graph call (see `docs/FRANKENPHP-WORKER-AUDIT.md`)
- No Twig templates in the bundle / no translation domains / no frontend assets
- Host apps own persistence; use events + `sendRaw()` to integrate

## Relationship to spec-driven development

Engram covers the org-wide checklist plane (documentation hygiene, MCP, agent memory). Product behavior and local `REQ-*` traceability live in [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) (user stories, scope, validation commands, requirement identifiers) and in the GitHub Spec Kit baseline (`specs/001-baseline/`). Use Engram for cross-session context; use the spec-driven doc as the functional contract.
