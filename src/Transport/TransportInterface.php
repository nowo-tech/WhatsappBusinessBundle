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
     * @param array<string, mixed> $graphBody
     *
     * @return array<string, mixed>
     */
    public function send(array $graphBody): array;

    public function markAsRead(string $messageId): void;

    public function uploadMedia(string $filePath, string $mimeType): MediaId;

    public function downloadMedia(string $mediaId): MediaDownload;

    /**
     * @return list<MessageTemplate>
     */
    public function listTemplates(): array;
}
