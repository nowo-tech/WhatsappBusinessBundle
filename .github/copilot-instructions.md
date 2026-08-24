# Copilot / AI contribution instructions — WhatsappBusinessBundle

## Scope

- This is a **Symfony bundle** for the public WhatsApp Cloud API (`nowo-tech/whatsapp-business-bundle` on Packagist).
- Namespace: `Nowo\WhatsappBusinessBundle`
- Config alias: `nowo_whatsapp_business` (named profiles + HTTP timeouts)
- Main services: `WhatsappClientInterface`, `WhatsappClientRegistryInterface`

## Rules

- English only for docs and comments (REQ-DOCS-016).
- Keep PHPUnit coverage at 100% for `src/`.
- Do not commit secrets, access tokens, or `.env` files.
- Never log access tokens, app secrets, or full phone numbers.
- Do not add Cursor co-author trailers to commits (REQ-GIT-001).
- Prefer `use` imports over leading-backslash FQCN (REQ-CS-001).
- Do not copy proprietary host-application WhatsApp code into this repository.

## References

- `docs/ENGRAM.md`, `docs/CONFIGURATION.md`, `docs/USAGE.md`
- `specs/001-baseline/`
