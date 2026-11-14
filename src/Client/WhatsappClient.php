<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Client;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Config\TransportKind;
use Nowo\WhatsappBusinessBundle\Event\MessageSendFailedEvent;
use Nowo\WhatsappBusinessBundle\Event\MessageSentEvent;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MediaMessage;
use Nowo\WhatsappBusinessBundle\Message\MediaSource;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\OutgoingMessage;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\RawGraphMessage;
use Nowo\WhatsappBusinessBundle\Message\TemplateMessage;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Transport\ProfileAwareTransportInterface;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Default client: transport + events + redacted logs.
 *
 * For a `custom` profile (host transport) every send failure is reported like a Graph failure: anything the host
 * throws becomes a {@see GraphApiException} (see {@see GraphApiException::hostTransportFailure()}) and a response
 * without `messages[0].id` is a failure ({@see GraphApiException::invalidSendResponse()}). `cloud` and `mock`
 * profiles keep their behaviour.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WhatsappClient implements WhatsappClientInterface
{
    private TransportInterface $transport;

    /**
     * Creates the client for one profile on top of its transport, optional event dispatcher and logger.
     *
     * A {@see ProfileAwareTransportInterface} is bound to this profile once, here (one host service can serve
     * several profiles).
     */
    public function __construct(
        private CloudApiProfile $profile,
        TransportInterface $transport,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->transport = $transport instanceof ProfileAwareTransportInterface
            ? $transport->forProfile($profile->name)
            : $transport;
    }

    /**
     * Returns the Cloud API profile this client sends with.
     */
    public function profile(): CloudApiProfile
    {
        return $this->profile;
    }

    /**
     * Sends a prepared envelope, dispatches MessageSentEvent / MessageSendFailedEvent and logs a redacted summary.
     */
    public function send(OutgoingEnvelope $envelope): MessageResult
    {
        $body = $envelope->toGraphBody();

        try {
            $result = $this->profile->transport === TransportKind::Custom
                ? $this->sendThroughHost($body)
                : MessageResult::fromGraph($this->transport->send($body));
        } catch (GraphApiException $exception) {
            $this->eventDispatcher?->dispatch(new MessageSendFailedEvent($this->profile->name, $envelope, $exception));
            $this->logger->error('WhatsApp send failed.', [
                'profile'   => $this->profile->name,
                'to'        => $envelope->to->masked(),
                'type'      => $envelope->message->type()->value,
                'http'      => $exception->httpStatus,
                'code'      => $exception->graphCode,
                'permanent' => $exception->isPermanent(),
            ]);
            throw $exception;
        }

        $this->eventDispatcher?->dispatch(new MessageSentEvent($this->profile->name, $envelope, $result));
        $this->logger->info('WhatsApp message sent.', [
            'profile'    => $this->profile->name,
            'to'         => $envelope->to->masked(),
            'type'       => $envelope->message->type()->value,
            'message_id' => $result->messageId,
        ]);

        return $result;
    }

    /**
     * Sends through a host transport and enforces its contract (audit PH L-2 / L-3).
     *
     * @param array<string, mixed> $body
     *
     * @throws GraphApiException for every host failure and for a response without a message id
     */
    private function sendThroughHost(array $body): MessageResult
    {
        try {
            $result = MessageResult::fromGraph($this->transport->send($body));
        } catch (GraphApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw GraphApiException::hostTransportFailure($exception);
        }
        if ($result->messageId === '') {
            throw GraphApiException::invalidSendResponse();
        }

        return $result;
    }

    /**
     * Parses the recipient phone number and sends a typed outbound message.
     */
    public function sendMessage(string $to, OutgoingMessage $message): MessageResult
    {
        return $this->send(new OutgoingEnvelope(PhoneNumber::parse($to), $message));
    }

    /**
     * Sends a plain-text message, optionally with link previews.
     */
    public function sendText(string $to, string $body, bool $previewUrl = false): MessageResult
    {
        return $this->sendMessage($to, new TextMessage($body, $previewUrl));
    }

    /**
     * Sends an approved message template with positional body parameters.
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParameters = []): MessageResult
    {
        return $this->sendMessage($to, new TemplateMessage($name, $language, $bodyParameters));
    }

    /**
     * Sends an image (uploaded media id or https link) with an optional caption.
     */
    public function sendImage(string $to, MediaSource $source, ?string $caption = null): MessageResult
    {
        return $this->sendMessage($to, MediaMessage::image($source, $caption));
    }

    /**
     * Sends host-built Graph JSON; transport fields (`to`, `type`, `messaging_product`) are normalised.
     */
    public function sendRaw(string $to, array $graphBody): MessageResult
    {
        $phone    = PhoneNumber::parse($to);
        $envelope = new OutgoingEnvelope($phone, RawGraphMessage::fromGraphBody($graphBody));

        return $this->send($envelope);
    }

    /**
     * Marks an inbound message as read.
     */
    public function markAsRead(string $messageId): void
    {
        $this->transport->markAsRead($messageId);
    }

    /**
     * Uploads a local file to the Cloud API media endpoint and returns its media id.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        return $this->transport->uploadMedia($filePath, $mimeType);
    }

    /**
     * Downloads media by id (Graph lookup + allow-listed URL download).
     */
    public function downloadMedia(string $mediaId): MediaDownload
    {
        return $this->transport->downloadMedia($mediaId);
    }

    /**
     * Lists the message templates of the profile WhatsApp Business Account.
     */
    public function listTemplates(): array
    {
        return $this->transport->listTemplates();
    }
}
