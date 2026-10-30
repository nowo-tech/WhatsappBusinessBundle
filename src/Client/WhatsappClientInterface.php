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
    /**
     * Returns the Cloud API profile this client sends with.
     */
    public function profile(): CloudApiProfile;

    /**
     * Sends a prepared envelope (recipient, message and optional extras).
     */
    public function send(OutgoingEnvelope $envelope): MessageResult;

    /**
     * Sends a typed outbound message to a phone number (E.164, with or without +).
     */
    public function sendMessage(string $to, OutgoingMessage $message): MessageResult;

    /**
     * Sends a plain-text message, optionally with link previews.
     */
    public function sendText(string $to, string $body, bool $previewUrl = false): MessageResult;

    /**
     * Sends an approved message template with positional body parameters.
     *
     * @param list<string> $bodyParameters
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParameters = []): MessageResult;

    /**
     * Sends an image (uploaded media id or https link) with an optional caption.
     */
    public function sendImage(string $to, MediaSource $source, ?string $caption = null): MessageResult;

    /**
     * Host-built Graph JSON (without `to` / `messaging_product` if already present).
     *
     * @param array<string, mixed> $graphBody
     */
    public function sendRaw(string $to, array $graphBody): MessageResult;

    /**
     * Marks an inbound message as read.
     */
    public function markAsRead(string $messageId): void;

    /**
     * Uploads a local file to the Cloud API media endpoint and returns its media id.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId;

    /**
     * Downloads media by id.
     */
    public function downloadMedia(string $mediaId): MediaDownload;

    /**
     * Lists the message templates of the profile WhatsApp Business Account.
     *
     * @return list<MessageTemplate>
     */
    public function listTemplates(): array;
}
