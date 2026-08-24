<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Client;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MediaSource;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\OutgoingMessage;
use Nowo\WhatsappBusinessBundle\Template\MessageTemplate;

/**
 * Public facade for one WhatsApp Cloud API profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface WhatsappClientInterface
{
    public function profile(): CloudApiProfile;

    public function send(OutgoingEnvelope $envelope): MessageResult;

    public function sendMessage(string $to, OutgoingMessage $message): MessageResult;

    public function sendText(string $to, string $body, bool $previewUrl = false): MessageResult;

    /**
     * @param list<string> $bodyParameters
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParameters = []): MessageResult;

    public function sendImage(string $to, MediaSource $source, ?string $caption = null): MessageResult;

    /**
     * Host-built Graph JSON (without `to` / `messaging_product` if already present).
     *
     * @param array<string, mixed> $graphBody
     */
    public function sendRaw(string $to, array $graphBody): MessageResult;

    public function markAsRead(string $messageId): void;

    public function uploadMedia(string $filePath, string $mimeType): MediaId;

    public function downloadMedia(string $mediaId): MediaDownload;

    /**
     * @return list<MessageTemplate>
     */
    public function listTemplates(): array;
}
