<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Transport;

use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Template\MessageTemplate;

/**
 * Outbound Cloud API operations for one profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface TransportInterface
{
    /**
     * Sends a full Graph message body and returns the Graph response.
     *
     * @param array<string, mixed> $graphBody
     *
     * @return array<string, mixed>
     */
    public function send(array $graphBody): array;

    /**
     * Marks an inbound message as read.
     */
    public function markAsRead(string $messageId): void;

    /**
     * Uploads a local file and returns its media id.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId;

    /**
     * Downloads media by id.
     */
    public function downloadMedia(string $mediaId): MediaDownload;

    /**
     * Lists the message templates of the profile WABA.
     *
     * @return list<MessageTemplate>
     */
    public function listTemplates(): array;
}
