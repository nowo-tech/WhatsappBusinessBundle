<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Exception;

/**
 * Thrown when a recipient or sender phone number is not Cloud API compatible.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class InvalidPhoneNumberException extends WhatsappBusinessException
{
    public static function invalid(): self
    {
        return new self('Phone number must be 8–15 digits (E.164 without +).');
    }
}
