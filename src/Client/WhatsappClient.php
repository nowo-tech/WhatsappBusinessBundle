<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Client;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
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
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default client: transport + events + redacted logs.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WhatsappClient implements WhatsappClientInterface
{
    public function __construct(
        private CloudApiProfile $profile,
        private TransportInterface $transport,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function profile(): CloudApiProfile
    {
        return $this->profile;
    }

    public function send(OutgoingEnvelope $envelope): MessageResult
    {
        $body = $envelope->toGraphBody();

        try {
            $payload = $this->transport->send($body);
            $result  = MessageResult::fromGraph($payload);
        } catch (GraphApiException $exception) {
            $this->eventDispatcher?->dispatch(new MessageSendFailedEvent($this->profile->name, $envelope, $exception));
            $this->logger->error('WhatsApp send failed.', [
                'profile' => $this->profile->name,
                'to'      => $envelope->to->masked(),
                'type'    => $envelope->message->type()->value,
                'http'    => $exception->httpStatus,
                'code'    => $exception->graphCode,
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

    public function sendMessage(string $to, OutgoingMessage $message): MessageResult
    {
        return $this->send(new OutgoingEnvelope(PhoneNumber::parse($to), $message));
    }

    public function sendText(string $to, string $body, bool $previewUrl = false): MessageResult
    {
        return $this->sendMessage($to, new TextMessage($body, $previewUrl));
    }

    public function sendTemplate(string $to, string $name, string $language, array $bodyParameters = []): MessageResult
    {
        return $this->sendMessage($to, new TemplateMessage($name, $language, $bodyParameters));
    }

    public function sendImage(string $to, MediaSource $source, ?string $caption = null): MessageResult
    {
        return $this->sendMessage($to, MediaMessage::image($source, $caption));
    }

    public function sendRaw(string $to, array $graphBody): MessageResult
    {
        $phone    = PhoneNumber::parse($to);
        $envelope = new OutgoingEnvelope($phone, RawGraphMessage::fromGraphBody($graphBody));

        return $this->send($envelope);
    }

    public function markAsRead(string $messageId): void
    {
        $this->transport->markAsRead($messageId);
    }

    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        return $this->transport->uploadMedia($filePath, $mimeType);
    }

    public function downloadMedia(string $mediaId): MediaDownload
    {
        return $this->transport->downloadMedia($mediaId);
    }

    public function listTemplates(): array
    {
        return $this->transport->listTemplates();
    }
}
