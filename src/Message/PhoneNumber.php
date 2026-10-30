<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use Nowo\WhatsappBusinessBundle\Exception\InvalidPhoneNumberException;

use function preg_replace;
use function strlen;

/**
 * Cloud API destination: digits only, country code included, no leading +.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class PhoneNumber
{
    /** @param non-empty-string $digits */
    private function __construct(
        public string $digits,
    ) {
    }

    /**
     * Normalises a phone number (spaces, dashes, +, 00) and validates 8–15 digits.
     */
    public static function parse(string $value): self
    {
        $normalized = preg_replace('/[\s\-().]/', '', $value) ?? '';
        if (str_starts_with($normalized, '+')) {
            $normalized = substr($normalized, 1);
        } elseif (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        if (preg_match('/^\d{8,15}$/', $normalized) !== 1) {
            throw InvalidPhoneNumberException::invalid();
        }

        /* @var non-empty-string $normalized */
        return new self($normalized);
    }

    /**
     * Returns the number with every digit but the last four masked (for logs).
     */
    public function masked(): string
    {
        return str_repeat('*', strlen($this->digits) - 4) . substr($this->digits, -4);
    }
}
