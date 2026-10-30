<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Exception;

/**
 * Thrown when webhook signature or hub.verify_token checks fail.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookVerificationException extends WhatsappBusinessException
{
    /**
     * Creates the exception for an X-Hub-Signature-256 that does not match the body HMAC.
     */
    public static function invalidSignature(): self
    {
        return new self('Invalid WhatsApp webhook signature.');
    }

    /**
     * Creates the exception for a webhook without signature (or without app secret to check it).
     */
    public static function missingSignature(): self
    {
        return new self('Missing X-Hub-Signature-256 header.');
    }

    /**
     * Creates the exception for a subscribe request with a wrong hub.verify_token.
     */
    public static function invalidVerifyToken(): self
    {
        return new self('Invalid webhook verify token.');
    }
}
