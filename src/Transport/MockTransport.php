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

use function array_slice;
use function bin2hex;
use function count;
use function is_string;
use function max;
use function random_bytes;

/**
 * In-memory transport for demos, tests, and hosts without Cloud credentials.
 *
 * Recorded sends are bounded ({@see self::DEFAULT_MAX_RECORDED}) and cleared by {@see reset()}, which the
 * bundle calls at the start of every main request and on {@code kernel.reset}, so FrankenPHP workers never
 * expose (or accumulate) sends from earlier requests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MockTransport implements TransportInterface, ResetInterface
{
    public const DEFAULT_MAX_RECORDED = 100;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    private readonly int $maxRecorded;

    /**
     * Creates the mock transport keeping at most $maxRecorded entries.
     *
     * @param int $maxRecorded only the most recent entries are kept (minimum 1)
     */
    public function __construct(
        private readonly CloudApiProfile $profile,
        int $maxRecorded = self::DEFAULT_MAX_RECORDED,
    ) {
        $this->maxRecorded = max(1, $maxRecorded);
    }

    /**
     * Records the body in memory and returns a Graph-shaped response with a mock message id.
     */
    public function send(array $graphBody): array
    {
        $id = 'mock.' . bin2hex(random_bytes(8));
        $to = isset($graphBody['to']) && is_string($graphBody['to']) ? $graphBody['to'] : '';
        $this->record([
            'to'   => $to,
            'body' => $graphBody,
            'id'   => $id,
        ]);

        return [
            'messaging_product' => 'whatsapp',
            'contacts'          => [['input' => $to, 'wa_id' => $to]],
            'messages'          => [['id' => $id]],
        ];
    }

    /**
     * Records the read receipt in memory.
     */
    public function markAsRead(string $messageId): void
    {
        $this->record(['read' => $messageId]);
    }

    /**
     * Returns a mock media id without reading the file.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        $id = 'mock-media.' . bin2hex(random_bytes(6));

        return new MediaId($id, ['id' => $id, 'file' => $filePath, 'mime' => $mimeType]);
    }

    /**
     * Returns fixed mock bytes for the media id.
     */
    public function downloadMedia(string $mediaId): MediaDownload
    {
        return new MediaDownload('mock-bytes', 'application/octet-stream', 'https://example.invalid/media/' . $mediaId);
    }

    /**
     * Returns one approved mock template; requires the WABA id like the live transport.
     */
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
     * Returns the sends and read receipts recorded in the current request.
     *
     * @return list<array<string, mixed>>
     */
    public function recorded(): array
    {
        return $this->sent;
    }

    /**
     * Clears the recorded sends (kernel.reset and every main request).
     */
    public function reset(): void
    {
        $this->sent = [];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function record(array $entry): void
    {
        $this->sent[] = $entry;
        if (count($this->sent) > $this->maxRecorded) {
            $this->sent = array_slice($this->sent, -$this->maxRecorded);
        }
    }
}
