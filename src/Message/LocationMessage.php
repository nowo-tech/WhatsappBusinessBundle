<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Geographic pin.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class LocationMessage implements OutgoingMessage
{
    /**
     * Creates a location pin with optional name and address.
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $name = null,
        public ?string $address = null,
    ) {
    }

    /**
     * Returns the location message type.
     */
    public function type(): MessageType
    {
        return MessageType::Location;
    }

    /**
     * Returns the Graph `location` field (empty name/address are omitted).
     */
    public function graphFields(): array
    {
        $location = [
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
        ];
        if ($this->name !== null && $this->name !== '') {
            $location['name'] = $this->name;
        }
        if ($this->address !== null && $this->address !== '') {
            $location['address'] = $this->address;
        }

        return ['location' => $location];
    }
}
