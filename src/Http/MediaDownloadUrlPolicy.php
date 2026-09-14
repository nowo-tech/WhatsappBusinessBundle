<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use function is_array;
use function is_string;
use function str_ends_with;
use function strtolower;

use const PHP_URL_HOST;

/**
 * Allowlist for Cloud API media download URLs so the access token is never sent off-Meta.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MediaDownloadUrlPolicy
{
    /**
     * @var list<string>
     */
    private const META_HOST_SUFFIXES = [
        'facebook.com',
        'instagram.com',
        'fbsbx.com',
        'fbcdn.net',
        'whatsapp.net',
        'whatsapp.com',
        'cdninstagram.com',
    ];

    public static function isAllowed(string $url, string $graphBaseUri): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);

        $graphHost = parse_url($graphBaseUri, PHP_URL_HOST);
        if (is_string($graphHost) && $graphHost !== '' && self::hostMatches($host, strtolower($graphHost))) {
            return true;
        }

        foreach (self::META_HOST_SUFFIXES as $suffix) {
            if (self::hostMatches($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private static function hostMatches(string $host, string $allowed): bool
    {
        return $host === $allowed || str_ends_with($host, '.' . $allowed);
    }
}
