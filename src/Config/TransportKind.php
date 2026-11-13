<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Config;

use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;

/**
 * How a profile talks to WhatsApp: live Graph API, an in-memory mock, or a host implementation.
 *
 * `custom` (since 1.4) means the profile is served by the service named in its `transport_service` key, which
 * must implement {@see TransportInterface} (REQ-ARCH-001 rule 5). `cloud` and `mock` are unchanged.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum TransportKind: string
{
    case Cloud  = 'cloud';
    case Mock   = 'mock';
    case Custom = 'custom';
}
