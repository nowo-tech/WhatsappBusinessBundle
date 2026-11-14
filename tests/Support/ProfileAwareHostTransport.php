<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Support;

use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Transport\ProfileAwareTransportInterface;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;

/**
 * One host service shared by several profiles (audit PH I-1): each profile gets its own stateless
 * {@see HostTransport} whose ids start with the profile name.
 */
final readonly class ProfileAwareHostTransport implements ProfileAwareTransportInterface
{
    /**
     * Returns the transport bound to $profile.
     */
    public function forProfile(string $profile): TransportInterface
    {
        return new HostTransport($profile);
    }

    /**
     * Used only when the bundle does not bind a profile (never through WhatsappClient).
     */
    public function send(array $graphBody): array
    {
        return ['messages' => [['id' => 'unbound.sent']]];
    }

    /**
     * No-op.
     */
    public function markAsRead(string $messageId): void
    {
    }

    /**
     * Returns an unbound media id.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        return new MediaId('unbound.media', []);
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
