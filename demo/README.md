# Demos

FrankenPHP demos for **WhatsappBusinessBundle**.

| Demo | Symfony | Path |
|------|---------|------|
| Symfony 8 | 8.1 | [`demo/symfony8`](symfony8/) |

## Quick start

From the **bundle root**:

```bash
make -C demo up-symfony8
```

Or from a demo folder:

```bash
cd demo/symfony8
make up
```

`make up` copies `.env.example` → `.env` if needed, starts Compose, installs Composer deps, and prints:

`Demo started at: http://localhost:<PORT>`

FrankenPHP docs: [../docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).

## What the demo shows

- Injected `WhatsappClientInterface` with the **mock** transport (no Meta credentials required)
- Symfony form (`form_start` / child-loop `form_row` / `form_end`) to send a text message
- Webhook route `/whatsapp/webhook`
- Profile timeout values on the page (REQ-RUNTIME-001)
