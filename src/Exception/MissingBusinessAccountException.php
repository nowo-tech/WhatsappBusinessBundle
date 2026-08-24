<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Exception;

/**
 * Thrown when template management is requested without a WABA id.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MissingBusinessAccountException extends WhatsappBusinessException
{
    public static function forProfile(string $profile): self
    {
        return new self('whatsapp_business_account_id is required for template management on profile "' . $profile . '".');
    }
}
