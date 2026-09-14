<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Config;

use Nowo\WhatsappBusinessBundle\Exception\MissingBusinessAccountException;
use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;
use SensitiveParameter;

/**
 * Immutable Cloud API credentials and HTTP limits for one named profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CloudApiProfile
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $phoneNumberId
     * @param non-empty-string $graphVersion
     * @param non-empty-string $graphBaseUri
     */
    public function __construct(
        public string $name,
        public string $phoneNumberId,
        #[SensitiveParameter]
        public string $accessToken,
        public TransportKind $transport,
        public ?string $whatsappBusinessAccountId,
        public string $graphVersion,
        public string $graphBaseUri,
        public float $timeout,
        public float $connectTimeout,
        #[SensitiveParameter]
        public ?string $appSecret,
        #[SensitiveParameter]
        public ?string $webhookVerifyToken,
        public bool $allowUnsignedWebhooks,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name'                      => $this->name,
            'phoneNumberId'             => $this->phoneNumberId,
            'accessToken'               => SecretRedactor::PLACEHOLDER,
            'transport'                 => $this->transport,
            'whatsappBusinessAccountId' => $this->whatsappBusinessAccountId,
            'graphVersion'              => $this->graphVersion,
            'graphBaseUri'              => $this->graphBaseUri,
            'timeout'                   => $this->timeout,
            'connectTimeout'            => $this->connectTimeout,
            'appSecret'                 => $this->appSecret === null ? null : SecretRedactor::PLACEHOLDER,
            'webhookVerifyToken'        => $this->webhookVerifyToken === null ? null : SecretRedactor::PLACEHOLDER,
            'allowUnsignedWebhooks'     => $this->allowUnsignedWebhooks,
        ];
    }

    public function messagesPath(): string
    {
        return $this->phoneNumberId . '/messages';
    }

    public function mediaPath(): string
    {
        return $this->phoneNumberId . '/media';
    }

    public function templatesPath(): string
    {
        $wabaId = $this->whatsappBusinessAccountId;
        if ($wabaId === null || $wabaId === '') {
            throw MissingBusinessAccountException::forProfile($this->name);
        }

        return $wabaId . '/message_templates';
    }
}
