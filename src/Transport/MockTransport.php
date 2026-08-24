<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Transport;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Exception\MissingBusinessAccountException;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Template\MessageTemplate;
use Nowo\WhatsappBusinessBundle\Template\TemplateStatus;
use Symfony\Contracts\Service\ResetInterface;

use function bin2hex;
use function is_string;
use function random_bytes;

/**
 * In-memory transport for demos, tests, and hosts without Cloud credentials.
 *
 * Implements ResetInterface so FrankenPHP worker mode does not leak recorded sends.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MockTransport implements TransportInterface, ResetInterface
{
    /** @var list<array<string, mixed>> */
    private array $sent = [];

    public function __construct(
        private readonly CloudApiProfile $profile,
    ) {
    }

    public function send(array $graphBody): array
    {
        $id           = 'mock.' . bin2hex(random_bytes(8));
        $to           = isset($graphBody['to']) && is_string($graphBody['to']) ? $graphBody['to'] : '';
        $this->sent[] = [
            'to'   => $to,
            'body' => $graphBody,
            'id'   => $id,
        ];

        return [
            'messaging_product' => 'whatsapp',
            'contacts'          => [['input' => $to, 'wa_id' => $to]],
            'messages'          => [['id' => $id]],
        ];
    }

    public function markAsRead(string $messageId): void
    {
        $this->sent[] = ['read' => $messageId];
    }

    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        $id = 'mock-media.' . bin2hex(random_bytes(6));

        return new MediaId($id, ['id' => $id, 'file' => $filePath, 'mime' => $mimeType]);
    }

    public function downloadMedia(string $mediaId): MediaDownload
    {
        return new MediaDownload('mock-bytes', 'application/octet-stream', 'https://example.invalid/media/' . $mediaId);
    }

    public function listTemplates(): array
    {
        if ($this->profile->whatsappBusinessAccountId === null || $this->profile->whatsappBusinessAccountId === '') {
            throw MissingBusinessAccountException::forProfile($this->profile->name);
        }

        return [
            new MessageTemplate(
                'mock-template-1',
                'hello_world',
                TemplateStatus::Approved,
                'en_US',
                'UTILITY',
                ['id' => 'mock-template-1', 'name' => 'hello_world'],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recorded(): array
    {
        return $this->sent;
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
