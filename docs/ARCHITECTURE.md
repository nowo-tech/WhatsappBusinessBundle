# Architecture

WhatsappBusinessBundle is an **infrastructure** bundle: a WhatsApp Cloud API client (one per profile), signed webhook
handling, a Messenger message for async sending and two console commands. It has **no Doctrine entities, no mapped
superclasses and no traits**, so there is nothing to map and it never touches the host EntityManager
(REQ-ARCH-001 rules 2 and 6 do not apply). Configuration comes from the config tree (`nowo_whatsapp_business`), never from
InstanceSettings (rule 8). Everything the domain sees is an interface, a value object or an event carrying value objects.

## Table of contents

- [Ports and extension points (SPI)](#ports-and-extension-points-spi)
- [Events](#events)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)

## Ports and extension points (SPI)

| Port | Shipped implementation | Service id | Use it to |
|------|------------------------|------------|-----------|
| `Client\WhatsappClientInterface` | `WhatsappClient` (one per profile) | alias of the default profile; `nowo_whatsapp_business.client.<profile>` | send messages, mark read, upload / download media, list templates |
| `Client\WhatsappClientRegistryInterface` | `WhatsappClientRegistry` | `nowo_whatsapp_business.registry` | select a client by profile name |
| `Transport\TransportInterface` | `CloudApiTransport` (Graph API) / `MockTransport` (in-memory) | `nowo_whatsapp_business.transport.<profile>` | replace how a profile reaches WhatsApp |
| `Transport\ProfileAwareTransportInterface` (since 1.4, optional) | none | your `transport_service` | serve several `custom` profiles from one service, bound per profile through `forProfile()` |

**Transport kinds.** `transport` is an enum (`Config\TransportKind`): `cloud` (Graph API, default), `mock` (in-memory,
reset through `kernel.reset`) and `custom` (since 1.4, the service named in `transport_service`).

**Custom transport (REQ-ARCH-001 rule 5).** Set `transport_service` on a profile to the id of your own service
implementing `Transport\TransportInterface`. `transport: custom` is inferred when `transport` is omitted. The bundle
aliases `nowo_whatsapp_business.transport.<profile>` to your service and never redefines, retags, decorates or resets
it: no `GraphApiClient` is built for the profile and no mock reset is registered, so your transport owns its
per-request state (REQ-RUNTIME-002: nothing kept between requests, or reset through `kernel.reset`).

Validation:

- `Configuration` refuses `transport: custom` without `transport_service`, and `transport_service` combined with
  `cloud` or `mock`.
- The extension refuses a `transport_service` equal to the profile's own transport id, and records
  `[profile => service id]` for the compiler pass.
- `DependencyInjection\Compiler\TransportServicePass` fails the container build when the service is not defined, its
  class does not implement `TransportInterface`, it is (or leads to through aliases and child-definition parents, at
  any depth) a `nowo_whatsapp_business.*` service such as another profile's transport, or it or any parent declares
  the bundle's `CloudApiTransport` / `MockTransport` class. Classes unknown before compilation (factories, synthetic
  services) are left to the container's own type checks. A host factory, decorator or delegating transport that
  injects a bundle transport cannot be detected at compile time and is the host's responsibility. `cloud` and `mock`
  profiles are not touched.
- At runtime `WhatsappClient` holds a `custom` profile to the host contract: any non-`GraphApiException` throwable
  becomes `GraphApiException::hostTransportFailure()` and a response without `messages[0].id` becomes
  `GraphApiException::invalidSendResponse()`, both with `MessageSendFailedEvent` and the error log
  ([contract](CONFIGURATION.md#contract-of-a-host-transport)).

```yaml
# config/packages/nowo_whatsapp_business.yaml
nowo_whatsapp_business:
    profiles:
        default:
            transport_service: App\Infrastructure\Whatsapp\OutboxTransport   # implies transport: custom
            phone_number_id: '%env(WHATSAPP_PHONE_NUMBER_ID)%'
            access_token: '%env(WHATSAPP_TOKEN)%'
```

Value objects: `Message\PhoneNumber`, `Message\OutgoingEnvelope`, the message types (`TextMessage`, `TemplateMessage`, ...),
`Message\MessageResult`, `Message\MediaId`, `Message\MediaDownload`, `Webhook\IncomingMessage`, `Webhook\StatusUpdate`,
`Webhook\WebhookNotification`, `Config\CloudApiProfile`. They carry scalars, enums and other value objects only.
`Messenger\SendOutgoingMessage` is the async message: it carries an `OutgoingEnvelope` and an optional profile name, no
HTTP or Doctrine type.

## Events

All events extend `Symfony\Contracts\EventDispatcher\Event` and carry **the profile name and value objects**, never a
Request, Response or entity:

| Event | Carries |
|-------|---------|
| `Event\MessageSentEvent` | profile, `OutgoingEnvelope`, `MessageResult` |
| `Event\MessageSendFailedEvent` | profile, `OutgoingEnvelope`, `GraphApiException` |
| `Event\WebhookReceivedEvent` | profile, `WebhookNotification` |
| `Event\IncomingMessageEvent` | profile, `IncomingMessage` |
| `Event\StatusUpdateEvent` | profile, `StatusUpdate` |

Events are dispatched after the Graph call or after the webhook body is verified and parsed; the bundle writes nothing
to a database, so there is no commit to wait for (rule 7). Correlate with your own records through
`OutgoingEnvelope::$bizOpaqueCallbackData` (returned on status webhooks).

## Standard Symfony and DDD hosts

**Standard Symfony host (default).** Configure a profile, import the webhook routes, inject `WhatsappClientInterface` (or
the registry) and subscribe to the events you need. Nothing else to wire.

**DDD / hexagonal host (opt-in).**

- Define your own port in the application layer, for example `CustomerNotifier::notify(CustomerId, Notification)`, and
  implement it in infrastructure with `WhatsappClientInterface` (or `SendOutgoingMessage` on your Messenger bus). Build
  `PhoneNumber` and `OutgoingEnvelope` there from your own ids and values; never pass entities into the bundle.
- Handle inbound traffic with event subscribers in infrastructure that translate `IncomingMessageEvent` /
  `StatusUpdateEvent` into your own application commands or domain events carrying ids. The subscribers are the
  anti-corruption layer; the domain never imports a bundle class.
- Persist what you need in your own repositories. The bundle keeps nothing; reply correlation uses
  `bizOpaqueCallbackData`.
- Plug a different delivery mechanism (an outbox, a provider other than Cloud API) with `transport_service`, keeping the
  client API unchanged. See also [USAGE](USAGE.md#host-architecture-standard-symfony-and-ddd--hexagonal-req-arch-001)
  and [CONFIGURATION](CONFIGURATION.md).
- The webhook controller, the verifier (HMAC `X-Hub-Signature-256`) and the commands are HTTP / CLI edge adapters: keep
  them at the edge.
