# Usage

## Table of contents

- [Send a text](#send-a-text)
- [Templates and media](#templates-and-media)
- [sendRaw (host Graph JSON)](#sendraw-host-graph-json)
- [Registry](#registry)
- [Events](#events)
- [Messenger](#messenger)
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

Dispatch `Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage`. The handler rethrows Graph errors so Messenger retry / failure transport can run.

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
