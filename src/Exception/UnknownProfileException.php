<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Exception;

use function sprintf;

/**
 * Thrown when a named profile does not exist.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class UnknownProfileException extends WhatsappBusinessException
{
    public static function forProfile(string $name): self
    {
        return new self(sprintf('Unknown WhatsApp Business profile "%s".', $name));
    }
}
