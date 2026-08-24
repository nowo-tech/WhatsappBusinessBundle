<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Config;

/**
 * How a profile talks to WhatsApp: live Graph API or an in-memory mock.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum TransportKind: string
{
    case Cloud = 'cloud';
    case Mock  = 'mock';
}
