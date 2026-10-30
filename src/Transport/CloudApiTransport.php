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
    /**
     * Creates the live transport for one profile on top of its Graph client.
     */
    public function __construct(
        private GraphApiClient $graph,
        private CloudApiProfile $profile,
    ) {
    }

    /**
     * Posts the message body to the Graph messages endpoint.
     */
    public function send(array $graphBody): array
    {
        return $this->graph->postJson($this->profile->messagesPath(), $graphBody);
    }

    /**
     * Marks an inbound message as read through the Graph messages endpoint.
     */
    public function markAsRead(string $messageId): void
    {
        $this->graph->postJson($this->profile->messagesPath(), [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ]);
    }

    /**
     * Uploads a local file to the Graph media endpoint.
     */
    public function uploadMedia(string $filePath, string $mimeType): MediaId
    {
        $payload = $this->graph->postMedia($this->profile->mediaPath(), $filePath, $mimeType);
        $id      = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';

        return new MediaId($id, $payload);
    }

    /**
     * Resolves the media URL by id (validated id), then downloads it through the allow-list.
     */
    public function downloadMedia(string $mediaId): MediaDownload
    {
        // The id becomes a Graph URL path segment: reject "/", "?", "..", "%2F", etc.
        // "D" (dollar end only): without it "$" also matches before a trailing "\n".
        if (preg_match('/^[0-9A-Za-z_-]+$/D', $mediaId) !== 1) {
            throw InvalidMediaIdException::invalid();
        }

        $meta = $this->graph->get($mediaId);
        $url  = isset($meta['url']) && is_string($meta['url']) ? $meta['url'] : null;
        $mime = isset($meta['mime_type']) && is_string($meta['mime_type']) ? $meta['mime_type'] : null;
        $body = $url !== null ? $this->graph->downloadRaw($url) : '';

        return new MediaDownload($body, $mime, $url);
    }

    /**
     * Lists the message templates of the profile WABA.
     */
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
