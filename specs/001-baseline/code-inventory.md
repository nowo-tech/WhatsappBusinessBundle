# Code inventory (baseline)

**Last audited:** 2026-10-20  
**Production units:** 60 (every file tracked under `src/`; audit: `git ls-files src | wc -l`)  
**Spec:** [`spec.md`](spec.md)

Categorized `src/` map for WhatsappBusinessBundle; paths are relative to `src/`. PHP line coverage is 100% (`make test-coverage`).

## Bundle / DI and config

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Config/CloudApiProfile.php` | Named profile value object (HTTPS Graph base URI, business account) | FR-CFG-001, FR-ERR-001 |
| `Config/TransportKind.php` | Transport enum (`cloud` / `mock`) | FR-CFG-001, FR-HTTP-004 |
| `DependencyInjection/Configuration.php` | Config tree `nowo_whatsapp_business` | FR-CFG-001 |
| `DependencyInjection/WhatsappBusinessExtension.php` | DI extension | FR-CFG-002 |
| `Resources/config/console.yaml` | Console command wiring | FR-CFG-002, FR-ASYNC-002 |
| `Resources/config/messenger.yaml` | Messenger handler wiring | FR-CFG-002, FR-ASYNC-001 |
| `Resources/config/routing.yaml` | Webhook routes | FR-CFG-002, FR-HOOK-002 |
| `Resources/config/services.yaml` | Service wiring | FR-CFG-002 |
| `WhatsappBusinessBundle.php` | Bundle entry | FR-CFG-002 |

## Client

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Client/WhatsappClient.php` | Typed sends, sendRaw, media, templates | FR-HTTP-002 |
| `Client/WhatsappClientInterface.php` | Public client contract | FR-HTTP-002 |
| `Client/WhatsappClientRegistry.php` | Profile registry | FR-HTTP-002, FR-ERR-001 |
| `Client/WhatsappClientRegistryInterface.php` | Profile registry contract | FR-HTTP-002 |

## HTTP and transport

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Http/GraphApiClient.php` | Graph HTTP client with timeouts | FR-HTTP-001 |
| `Http/GraphApiException.php` | Graph API error | FR-HTTP-001 |
| `Http/MediaDownloadUrlPolicy.php` | Meta-only media download allowlist | FR-HTTP-003 |
| `Http/SecretRedactor.php` | Secret masking in logs | FR-HTTP-003 |
| `Http/SensitiveDumpRegistrar.php` | VarDumper casters hiding secrets | FR-HTTP-003 |
| `Transport/CloudApiTransport.php` | Real Graph transport | FR-HTTP-004 |
| `Transport/MockTransport.php` | Mock transport with bounded recordings | FR-HTTP-004, FR-RUN-001 |
| `Transport/TransportInterface.php` | Transport contract | FR-HTTP-004 |

## Event subscribers

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/ResetMockTransportsOnRequestSubscriber.php` | Clears mock recordings on each main request | FR-RUN-001 |

## Messages

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Message/GraphPayloadMessage.php` | Host-supplied Graph-shaped contacts/interactive payload | FR-MSG-001 |
| `Message/LocationMessage.php` | Location message | FR-MSG-001 |
| `Message/MediaDownload.php` | Downloaded media | FR-MSG-002, FR-HTTP-003 |
| `Message/MediaId.php` | Validated media id | FR-MSG-001 |
| `Message/MediaMessage.php` | Media message | FR-MSG-001 |
| `Message/MediaSource.php` | Media by id or link | FR-MSG-001 |
| `Message/MessageResult.php` | Send result | FR-MSG-002 |
| `Message/MessageType.php` | Message type enum | FR-MSG-001 |
| `Message/OutgoingEnvelope.php` | Recipient + message + Cloud API extras | FR-MSG-001 |
| `Message/OutgoingMessage.php` | Outbound message contract | FR-MSG-001 |
| `Message/PhoneNumber.php` | Validated recipient | FR-MSG-001 |
| `Message/RawGraphMessage.php` | Raw Graph JSON (`sendRaw()`) | FR-MSG-001, FR-HTTP-002 |
| `Message/ReactionMessage.php` | Reaction message | FR-MSG-001 |
| `Message/TemplateMessage.php` | Template message | FR-MSG-001 |
| `Message/TextMessage.php` | Text message | FR-MSG-001 |

## Templates

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Template/MessageTemplate.php` | Template listing item | FR-MSG-002 |
| `Template/TemplateStatus.php` | Template status enum | FR-MSG-002 |

## Webhooks

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Webhook/IncomingMessage.php` | Inbound message DTO | FR-HOOK-002 |
| `Webhook/StatusUpdate.php` | Delivery status DTO | FR-HOOK-002 |
| `Webhook/WebhookController.php` | Webhook endpoint | FR-HOOK-002 |
| `Webhook/WebhookNotification.php` | Parsed notification DTO | FR-HOOK-002 |
| `Webhook/WebhookParser.php` | Parse Meta notifications | FR-HOOK-002 |
| `Webhook/WebhookVerifier.php` | Verify token + HMAC `X-Hub-Signature-256` | FR-HOOK-001 |

## Events

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/IncomingMessageEvent.php` | Inbound message event | FR-HOOK-002 |
| `Event/MessageSendFailedEvent.php` | Send failure event | FR-ASYNC-001 |
| `Event/MessageSentEvent.php` | Send success event | FR-ASYNC-001 |
| `Event/StatusUpdateEvent.php` | Status update event | FR-HOOK-002 |
| `Event/WebhookReceivedEvent.php` | Raw webhook event | FR-HOOK-002 |

## Messenger and console

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/ListTemplatesCommand.php` | `whatsapp:templates` | FR-ASYNC-002 |
| `Command/SendTextCommand.php` | `whatsapp:send-text` | FR-ASYNC-002 |
| `Messenger/SendOutgoingMessage.php` | Async send message | FR-ASYNC-001 |
| `Messenger/SendOutgoingMessageHandler.php` | Async send handler | FR-ASYNC-001 |

## Exceptions

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/InvalidMediaIdException.php` | Invalid media id | FR-ERR-001, FR-MSG-001 |
| `Exception/InvalidPhoneNumberException.php` | Invalid phone number | FR-ERR-001, FR-MSG-001 |
| `Exception/MissingBusinessAccountException.php` | Profile without business account | FR-ERR-001 |
| `Exception/UnknownProfileException.php` | Unknown profile | FR-ERR-001, FR-HTTP-002 |
| `Exception/WebhookVerificationException.php` | Webhook verification failure | FR-ERR-001, FR-HOOK-001 |
| `Exception/WhatsappBusinessException.php` | Base exception | FR-ERR-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle / DI and config | 9 | 9 |
| Client | 4 | 4 |
| HTTP and transport | 8 | 8 |
| Event subscribers | 1 | 1 |
| Messages | 15 | 15 |
| Templates | 2 | 2 |
| Webhooks | 6 | 6 |
| Events | 5 | 5 |
| Messenger and console | 4 | 4 |
| Exceptions | 6 | 6 |
| **Total production files (`git ls-files src`)** | **60** | **60** |
