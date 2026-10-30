# Usage

## Table of contents

- [Send a text](#send-a-text)
- [Templates and media](#templates-and-media)
- [sendRaw (host Graph JSON)](#sendraw-host-graph-json)
- [Registry](#registry)
- [Events](#events)
- [Messenger](#messenger)
  - [Transport expectations](#transport-expectations)
  - [Retries and failure transport](#retries-and-failure-transport)
  - [Idempotency](#idempotency)
  - [Logging](#logging)
- [CLI](#cli)
- [Forms in host apps and demos (REQ-TWIG-005)](#forms-in-host-apps-and-demos-req-twig-005)

## Send a text

```php
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;

$client->sendText('34611111111', 'Hello');
```

Phone numbers are normalized to E.164 digits (optional `+` or `00` prefix is stripped).

## Templates and media

```php
use Nowo\WhatsappBusinessBundle\Message\MediaSource;

$client->sendTemplate('34611111111', 'hello_world', 'en_US', ['Ada']);
$client->sendImage('34611111111', MediaSource::link('https://example.com/n.png'), 'caption');
$id = $client->uploadMedia('/path/file.jpg', 'image/jpeg');
```

## sendRaw (host Graph JSON)

When the host already assembles Cloud API JSON:

```php
$client->sendRaw('34611111111', [
    'type' => 'text',
    'text' => ['body' => 'Hello', 'preview_url' => false],
]);
```

`messaging_product`, `recipient_type`, and `to` are filled by the client.

## Registry

```php
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;

$registry->get('test')->sendText($to, $body);
```

## Events

Subscribe to:

- `Nowo\WhatsappBusinessBundle\Event\MessageSentEvent`
- `Nowo\WhatsappBusinessBundle\Event\MessageSendFailedEvent`
- `Nowo\WhatsappBusinessBundle\Event\WebhookReceivedEvent`
- `Nowo\WhatsappBusinessBundle\Event\IncomingMessageEvent`
- `Nowo\WhatsappBusinessBundle\Event\StatusUpdateEvent`

The host should persist what it needs. The bundle does not write to the database.

## Messenger

Dispatch `Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage` (immutable `final readonly` message: an `OutgoingEnvelope` plus an optional profile name; `null` = default profile). `SendOutgoingMessageHandler` is registered with `#[AsMessageHandler]` (REQ-MSG-001) when `symfony/messenger` is installed and `framework.messenger` is enabled.

```php
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage;

$bus->dispatch(new SendOutgoingMessage(
    new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('Your order shipped')),
    'support', // optional profile
));
```

### Transport expectations

- **Default: synchronous.** Without routing, Messenger handles the message in the dispatching request (same as calling the client directly; the profile `timeout` bounds the Graph call).
- **Recommended for bulk / web requests: async.** Route the message to an async transport so HTTP workers (FrankenPHP worker mode included) never wait on Graph:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 5000        # ms; Meta 429 (code 130429) needs seconds, not milliseconds
                    multiplier: 3
                    max_delay: 300000
            failed: 'doctrine://default?queue_name=failed'
        routing:
            Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage: async
```

### Retries and failure transport

- The handler does **not** retry by itself and does not swallow errors. Transient failures — `GraphApiException` with HTTP 429, 5xx or status `0` (transport failure / timeout) — are rethrown, so Messenger applies the transport `retry_strategy` and finally moves the message to `failure_transport`.
- `MessageSendFailedEvent` is dispatched for every failed attempt (`GraphApiException` carries `httpStatus`, `graphCode`, `subcode`, `fbtraceId`; tokens are redacted).
- Permanent errors — Graph 4xx other than 429 (invalid recipient, template not approved, …) and `UnknownProfileException` — are wrapped in Symfony `UnrecoverableMessageHandlingException` (the original exception is `getPrevious()`): no retry, straight to the failure transport.
- Inspect / replay failures with `bin/console messenger:failed:show` and `messenger:failed:retry`.

### Idempotency

The handler is **not** idempotent: if Meta accepted the message but the worker died before acknowledging the queue message, a redelivery sends the WhatsApp message again (the Cloud API has no idempotency key). Mitigations:

- keep `max_retries` small and rely on the failure transport for manual review;
- put a host-side id in `OutgoingEnvelope::$bizOpaqueCallbackData` and deduplicate before dispatching (the value comes back on status webhooks, so `StatusUpdateEvent` listeners can reconcile);
- listen to `MessageSentEvent` (Graph `messageId`) to record what was already delivered.

### Logging

`WhatsappClient` logs every send with the profile, masked recipient, message type and Graph message id (`info`) or HTTP status and Graph error code (`error`) — never tokens or message bodies (REQ-OBS-001).

## CLI

```bash
php bin/console whatsapp:send-text 34611111111 "Hello" --profile=default
php bin/console whatsapp:templates --profile=default
```

## Forms in host apps and demos (REQ-TWIG-005)

The bundle ships no page templates of its own; host apps call its services from their own controllers and forms. Every form in host apps and in the demo is a PHP Form Type created in the controller and rendered only with the Twig form helpers and the rendered-aware child loop (REQ-TWIG-003):

```twig
{{ form_start(form) }}
    {% for child in form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit">Submit</button>
{{ form_end(form) }}
```

Hosts and demos must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>` markup; `make check-no-raw-html-form` (part of `make release-check`) fails when bundle or demo Twig contains them.
