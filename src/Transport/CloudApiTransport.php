<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Transport;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Exception\InvalidMediaIdException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Template\MessageTemplate;

use function is_array;
use function is_string;
use function preg_match;

/**
 * Live WhatsApp Cloud API (Graph) transport.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CloudApiTransport implements TransportInterface
{
    public function __construct(
        private GraphApiClient $graph,
        private CloudApiProfile $profile,
    ) {
    }

    public function send(array $graphBody): array
    {
        return $this->graph->postJson($this->profile->messagesPath(), $graphBody);
    }

    public function markAsRead(string $messageId): void
    {
        $this->graph->postJson($this->profile->messagesPath(), [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ]);
    }

    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        $payload = $this->graph->postMedia($this->profile->mediaPath(), $filePath, $mimeType);
        $id      = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';

        return new MediaId($id, $payload);
    }

    public function downloadMedia(string $mediaId): MediaDownload
    {
        // The id becomes a Graph URL path segment: reject "/", "?", "..", "%2F", etc.
        if (preg_match('/^[0-9A-Za-z_-]+$/', $mediaId) !== 1) {
            throw InvalidMediaIdException::invalid();
        }

        $meta = $this->graph->get($mediaId);
        $url  = isset($meta['url']) && is_string($meta['url']) ? $meta['url'] : null;
        $mime = isset($meta['mime_type']) && is_string($meta['mime_type']) ? $meta['mime_type'] : null;
        $body = $url !== null ? $this->graph->downloadRaw($url) : '';

        return new MediaDownload($body, $mime, $url);
    }

    public function listTemplates(): array
    {
        $payload = $this->graph->get($this->profile->templatesPath());
        $data    = $payload['data'] ?? [];
        $list    = [];
        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $item) {
            if (is_array($item)) {
                $list[] = MessageTemplate::fromGraph($item);
            }
        }

        return $list;
    }
}
