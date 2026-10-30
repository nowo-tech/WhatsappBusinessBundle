<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Template;

/**
 * Message template status from GET /{waba-id}/message_templates.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum TemplateStatus: string
{
    case Approved = 'APPROVED';
    case Pending  = 'PENDING';
    case Rejected = 'REJECTED';
    case Paused   = 'PAUSED';
    case Disabled = 'DISABLED';
    case Unknown  = 'UNKNOWN';

    /**
     * Maps a Graph template status (case-insensitive) to the enum; unknown values map to Unknown.
     */
    public static function fromGraph(string $value): self
    {
        return self::tryFrom(strtoupper($value)) ?? self::Unknown;
    }
}
