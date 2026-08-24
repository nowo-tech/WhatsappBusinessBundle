<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Exception\WebhookVerificationException;

use function hash_equals;
use function hash_hmac;
use function str_starts_with;
use function substr;

/**
 * Meta webhook hub.verify_token + X-Hub-Signature-256 checks.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookVerifier
{
    public function verifySubscribe(CloudApiProfile $profile, string $mode, string $token): bool
    {
        $expected = $profile->webhookVerifyToken;

        return $mode === 'subscribe' && $expected !== null && $expected !== '' && hash_equals($expected, $token);
    }

    public function verifySignature(CloudApiProfile $profile, string $rawBody, ?string $header): void
    {
        $secret = $profile->appSecret;
        if ($secret === null || $secret === '') {
            if ($profile->allowUnsignedWebhooks) {
                return;
            }
            throw WebhookVerificationException::missingSignature();
        }

        if ($header === null || $header === '') {
            throw WebhookVerificationException::missingSignature();
        }

        $provided = str_starts_with($header, 'sha256=') ? substr($header, 7) : $header;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $provided)) {
            throw WebhookVerificationException::invalidSignature();
        }
    }
}
