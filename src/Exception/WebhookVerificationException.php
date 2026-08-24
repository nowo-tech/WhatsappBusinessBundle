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
    public static function invalidSignature(): self
    {
        return new self('Invalid WhatsApp webhook signature.');
    }

    public static function missingSignature(): self
    {
        return new self('Missing X-Hub-Signature-256 header.');
    }

    public static function invalidVerifyToken(): self
    {
        return new self('Invalid webhook verify token.');
    }
}
