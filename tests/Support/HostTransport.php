<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Support;

use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;

/**
 * Stateless host implementation of {@see TransportInterface}, as a DDD / hexagonal host would write it: it keeps
 * nothing between calls, so it is safe in a kept-alive FrankenPHP worker without `kernel.reset` (REQ-RUNTIME-002).
 *
 * Used by the `transport: custom` tests of the extension, the compiler pass and the boot test.
 */
final readonly class HostTransport implements TransportInterface
{
    /**
     * Every identifier this transport returns starts with $prefix, so a test can prove the host service was used.
     */
    public function __construct(
        private string $prefix = 'host',
    ) {
    }

    /**
     * Returns a Graph-shaped response with a host message id; nothing is stored.
     */
    public function send(array $graphBody): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'contacts'          => [['input' => '', 'wa_id' => '']],
            'messages'          => [['id' => $this->prefix . '.sent']],
        ];
    }

    /**
     * Read receipts are a no-op for the host transport.
     */
    public function markAsRead(string $messageId): void
    {
    }

    /**
     * Returns a host media id without touching the filesystem.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        return new MediaId($this->prefix . '.media', ['id' => $this->prefix . '.media']);
    }

    /**
     * Returns fixed host bytes for the media id.
     */
    public function downloadMedia(string $mediaId): MediaDownload
    {
        return new MediaDownload($this->prefix . '-bytes', 'application/octet-stream', null);
    }

    /**
     * The host transport exposes no templates.
     */
    public function listTemplates(): array
    {
        return [];
    }
}
