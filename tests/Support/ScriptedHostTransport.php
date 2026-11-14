<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Support;

use Closure;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;

/**
 * Host transport whose `send()` runs a test closure: it returns a malformed payload or throws whatever a buggy or
 * failing host gateway would (audit PH L-2 / L-3).
 */
final readonly class ScriptedHostTransport implements TransportInterface
{
    /**
     * @param Closure(array<string, mixed>): array<string, mixed> $send
     */
    public function __construct(
        private Closure $send,
    ) {
    }

    /**
     * Runs the scripted closure.
     */
    public function send(array $graphBody): array
    {
        return ($this->send)($graphBody);
    }

    /**
     * No-op.
     */
    public function markAsRead(string $messageId): void
    {
    }

    /**
     * Returns a fixed media id.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        return new MediaId('scripted.media', []);
    }

    /**
     * Returns empty bytes.
     */
    public function downloadMedia(string $mediaId): MediaDownload
    {
        return new MediaDownload('', null, null);
    }

    /**
     * No templates.
     */
    public function listTemplates(): array
    {
        return [];
    }
}
