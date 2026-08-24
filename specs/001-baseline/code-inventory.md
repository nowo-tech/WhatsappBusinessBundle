# Code inventory (baseline)

**Last audited:** 2026-08-24. **Coverage summary:** PHP 100% statements in root `coverage.xml` (see `make test-coverage`).

Categorized `src/` map for WhatsappBusinessBundle. Update when adding types.

## Bundle / DI

- `WhatsappBusinessBundle.php`
- `DependencyInjection/Configuration.php`
- `DependencyInjection/WhatsappBusinessExtension.php`
- `Resources/config/services.yaml`
- `Resources/config/console.yaml`
- `Resources/config/messenger.yaml`
- `Resources/config/routing.yaml`

## Config

- `Config/CloudApiProfile.php`
- `Config/TransportKind.php`

## Client

- `Client/WhatsappClientInterface.php`
- `Client/WhatsappClient.php`
- `Client/WhatsappClientRegistryInterface.php`
- `Client/WhatsappClientRegistry.php`

## HTTP / transport

- `Http/GraphApiClient.php`
- `Http/GraphApiException.php`
- `Transport/TransportInterface.php`
- `Transport/CloudApiTransport.php`
- `Transport/MockTransport.php`

## Messages

- `Message/PhoneNumber.php`
- `Message/MessageType.php`
- `Message/OutgoingMessage.php`
- `Message/OutgoingEnvelope.php`
- `Message/TextMessage.php`
- `Message/TemplateMessage.php`
- `Message/MediaSource.php`
- `Message/MediaMessage.php`
- `Message/LocationMessage.php`
- `Message/ReactionMessage.php`
- `Message/GraphPayloadMessage.php`
- `Message/RawGraphMessage.php`
- `Message/MessageResult.php`
- `Message/MediaId.php`
- `Message/MediaDownload.php`

## Templates

- `Template/TemplateStatus.php`
- `Template/MessageTemplate.php`

## Webhooks

- `Webhook/WebhookVerifier.php`
- `Webhook/WebhookParser.php`
- `Webhook/WebhookController.php`
- `Webhook/IncomingMessage.php`
- `Webhook/StatusUpdate.php`
- `Webhook/WebhookNotification.php`

## Events / Messenger / Console

- `Event/*.php`
- `Messenger/SendOutgoingMessage.php`
- `Messenger/SendOutgoingMessageHandler.php`
- `Command/SendTextCommand.php`
- `Command/ListTemplatesCommand.php`

## Exceptions

- `Exception/*.php`
