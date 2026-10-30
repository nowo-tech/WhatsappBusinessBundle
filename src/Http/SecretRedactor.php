<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use function is_array;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Redacts Cloud API tokens, app secrets, and query credentials from dump/log surfaces.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SecretRedactor
{
    public const PLACEHOLDER = '[redacted]';

    /**
     * Replaces access tokens, Bearer credentials and secret query parameters with a placeholder.
     */
    public static function redactString(string $value): string
    {
        $value = preg_replace('/\bEAA[A-Za-z0-9]{8,}/', self::PLACEHOLDER, $value) ?? $value;
        $value = preg_replace('/Bearer\s+\S+/i', 'Bearer ' . self::PLACEHOLDER, $value) ?? $value;

        return preg_replace(
            '/(?i)((?:access_token|app_secret|client_secret|appsecret_proof|verify_token|hub\.verify_token)=)([^&\s]+)/',
            '$1' . self::PLACEHOLDER,
            $value,
        ) ?? $value;
    }

    /**
     * Redacts secret-named keys and secret-looking string values recursively.
     *
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    public static function redactArray(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            $name = (string) $key;
            if (preg_match('/token|secret|authorization|password|app_secret/i', $name) === 1 && is_string($item)) {
                $out[$key] = self::PLACEHOLDER;
                continue;
            }
            if (is_string($item)) {
                $out[$key] = self::redactString($item);
                continue;
            }
            if (is_array($item)) {
                /* @var array<string, mixed> $item */
                $out[$key] = self::redactArray($item);
                continue;
            }
            $out[$key] = $item;
        }

        return $out;
    }

    /**
     * Masks a phone number except its last four digits.
     */
    public static function maskPhone(string $value): string
    {
        $length = strlen($value);
        if ($length === 0) {
            return $value;
        }
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($value, -4);
    }

    /**
     * Keeps scheme, host and path of a URL and hides its query string.
     */
    public static function redactUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return self::PLACEHOLDER;
        }

        $scheme = $parts['scheme'] ?? '';
        $host   = $parts['host'] ?? '';
        $path   = $parts['path'] ?? '';
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '' || !is_string($path)) {
            return self::PLACEHOLDER;
        }

        $safe = $scheme . '://' . $host . $path;
        if (isset($parts['query'])) {
            $safe .= '?' . self::PLACEHOLDER;
        }

        return $safe;
    }
}
