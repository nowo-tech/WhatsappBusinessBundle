<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Cloud API `type` field for outbound messages.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum MessageType: string
{
    case Text        = 'text';
    case Template    = 'template';
    case Image       = 'image';
    case Audio       = 'audio';
    case Video       = 'video';
    case Document    = 'document';
    case Sticker     = 'sticker';
    case Location    = 'location';
    case Contacts    = 'contacts';
    case Interactive = 'interactive';
    case Reaction    = 'reaction';
}
