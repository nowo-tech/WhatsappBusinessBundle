<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Exception;

/**
 * Thrown when a media id is not a plain Graph node id (it is interpolated into the Graph URL path).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class InvalidMediaIdException extends WhatsappBusinessException
{
    /**
     * Creates the exception for a media id outside ^[0-9A-Za-z_-]+$.
     */
    public static function invalid(): self
    {
        return new self('Media id must match ^[0-9A-Za-z_-]+$.');
    }
}
