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
- [Host architecture: standard Symfony and DDD / hexagonal (REQ-ARCH-001)](#host-architecture-standard-symfony-and-ddd--hexagonal-req-arch-001)
  - [Standard Symfony host](#standard-symfony-host)
  - [DDD / hexagonal host](#ddd--hexagonal-host)
  - [What this bundle does not have](#what-this-bundle-does-not-have)

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
- Host transports (`transport_service`) follow the same rule through `GraphApiException::isPermanent()`: a host exception that is not a `GraphApiException` is retried (status `0`) unless it implements Messenger's `UnrecoverableExceptionInterface`, and a response without a message id is permanent. See [CONFIGURATION.md](CONFIGURATION.md#contract-of-a-host-transport).
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

## Host architecture: standard Symfony and DDD / hexagonal (REQ-ARCH-001)

Both host kinds are supported. The standard Symfony path is the zero-config default; the DDD / hexagonal path is
opt-in and never forced.

### Standard Symfony host

Nothing to configure beyond the profiles. Inject the bundle services where you need them:

```php
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;

public function __construct(
    private WhatsappClientInterface $whatsapp,          // the default profile
    private WhatsappClientRegistryInterface $profiles,  // any profile by name
) {
}
```

Listen to the bundle events with `#[AsEventListener]` or a subscriber, send asynchronously by routing
`SendOutgoingMessage` to a Messenger transport ([Messenger](#messenger)), and let the `cloud` transport talk to the
Graph API. The webhook controller is the bundle's own HTTP adapter; the routes stay opt-in
([CONFIGURATION.md](CONFIGURATION.md#webhooks)).

### DDD / hexagonal host

A host that keeps its domain free of framework types can use the bundle as a driven adapter behind its own port, and
can replace the driving adapter:

- **Everything the host touches is framework-free.** `WhatsappClientInterface`,
  `WhatsappClientRegistryInterface` and `TransportInterface` take and return only the bundle's own value objects —
  `OutgoingEnvelope`, `OutgoingMessage` (`TextMessage`, `TemplateMessage`, `MediaMessage`, `LocationMessage`,
  `ReactionMessage`, `RawGraphMessage`, `GraphPayloadMessage`), `PhoneNumber`, `MediaSource`, `MediaId`,
  `MediaDownload`, `MessageResult`, `MessageTemplate`, `CloudApiProfile` — plus scalars and arrays. No Doctrine type,
  no `Request` / `Response`, no `UserInterface`, no `EntityManagerInterface` appears in any of their signatures
  (REQ-ARCH-001 rule 3). The only framework packages in that surface are the interface-only
  `symfony/*-contracts`, which the rule allows: the events extend `Symfony\Contracts\EventDispatcher\Event` and
  `MockTransport` implements `Symfony\Contracts\Service\ResetInterface`.
- **The events carry ids, not entities** (rule 4): `MessageSentEvent`, `MessageSendFailedEvent`,
  `IncomingMessageEvent`, `StatusUpdateEvent` and `WebhookReceivedEvent` carry the profile **name** (a string) and
  bundle value objects (`OutgoingEnvelope`, `MessageResult`, `IncomingMessage`, `StatusUpdate`,
  `WebhookNotification`) or the bundle's own `GraphApiException`. Map them to your
  own domain events in one application-layer listener; your domain never imports the bundle.
- **Wrap the client behind your own port.** Declare the port in your domain
  (`interface NotifiesByWhatsapp { public function notify(string $e164, string $text): string; }`) and implement it in
  your infrastructure layer on top of `WhatsappClientInterface`. The bundle's value objects then stop at that
  boundary.
- **Replace the outbound adapter** (rule 5): implement `TransportInterface` and point the profile at it with
  `transport_service` ([CONFIGURATION.md](CONFIGURATION.md#host-transport-transport_service)). The bundle keeps the
  message building, the profile resolution, the events and the Messenger integration, and your implementation decides
  how the message leaves the process — an on-premise gateway, a BSP other than Meta, a record-and-replay fake in
  acceptance tests. `cloud` and `mock` stay available per profile. Your implementation must return a non-empty
  `messages[0].id`, throw `GraphApiException` to choose between retry and permanent failure, and do its own media-id
  and download-URL checks ([contract](CONFIGURATION.md#contract-of-a-host-transport)). One service named by several
  profiles is one shared instance with no profile context unless it implements `ProfileAwareTransportInterface`.
- **Configuration comes through the config tree** (rule 8), never from a database or InstanceSettings: `composer.json`
  requires neither `doctrine/orm` nor `nowo-tech/instance-settings-bundle`.
- **Worker mode (REQ-RUNTIME-002):** the ports are called on every send; nothing is memoised per request. A host
  transport must be stateless as well — the bundle does not reset it.

### What this bundle does not have

Rules 2, 6 and 7 of REQ-ARCH-001 are **not applicable**: the bundle ships no entity, no `#[ORM\MappedSuperclass]`,
no ORM trait and no table, it never touches an `EntityManager` (there is no `doctrine/*` package in `require`, and no
`->flush()` anywhere in `src/`), and it opens no transaction — so there is no host unit of work to break and no
"dispatch after commit" ordering to respect. Events are dispatched by the client right after the transport call and
by the webhook controller after the payload is verified.
