# Upgrading

## From 1.1.0 to 1.1.1

FrankenPHP worker hardening (safe under worker mode even when the kernel is not reset between requests). **No code changes required** for typical apps.

```bash
composer update nowo-tech/whatsapp-business-bundle
```

Behaviour change for profiles using `transport: mock` (including the zero-config default profile):

- `MockTransport::recorded()` is cleared at the start of every main HTTP request (sub-requests keep it), not only by `kernel.reset`. Read it in the same request that sent the messages; do not rely on it to carry sends from one request to the next.
- `recorded()` keeps only the most recent `MockTransport::DEFAULT_MAX_RECORDED` (100) entries. Code that builds `MockTransport` directly can pass a different limit as the second constructor argument.

## From 1.0.x to 1.1.0

Security hardening release. Most apps need **no code changes** if they already use HMAC webhooks and inject `WhatsappClientInterface` / `nowo_whatsapp_business.client`.

```bash
composer update nowo-tech/whatsapp-business-bundle
```

### Behaviour changes to review

1. **`allow_unsigned_webhooks` default is `false`** for the zero-config / default profile template.  
   - Production: keep `false` and set `app_secret`.  
   - Local demos without Meta signatures: set `allow_unsigned_webhooks: true` explicitly (the bundle demo already does).

2. **Named profile / graph / transport / client definitions are private.**  
   - Prefer constructor injection of `WhatsappClientInterface` or the public alias `nowo_whatsapp_business.client`.  
   - Do not `$container->get('nowo_whatsapp_business.profile.*')` (or `.graph.*` / `.transport.*` / `.client.*`) unless you mark them public in your own compiler pass.

3. **Media downloads** only accept HTTPS URLs on Meta hosts (or the host of your `graph_base_uri`). Off-allowlist URLs throw `GraphApiException` with a generic message; redirects are not followed.

4. **Webhook error HTTP bodies** are generic (`Unknown WhatsApp Business profile.` / `Invalid WhatsApp webhook signature.`). Rely on logs/status codes, not response text.

5. **Transport / Graph failure messages** no longer echo raw transport exception text (avoids leaking tokens mirrored in error strings).

## From 1.0.0 to 1.0.1

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/whatsapp-business-bundle
```

## 1.0.0

First public release. No upgrade path.
