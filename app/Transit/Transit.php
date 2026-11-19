<?php

namespace App\Transit;

final class Transit
{
    public const VERSION = '1.0.0';

    public const PROTOCOL_VERSION = 1;

    public static function url(): string
    {
        return self::normalize((string) config('transit.url'));
    }

    public static function endpoint(string $path): string
    {
        return self::url().'/'.ltrim($path, '/');
    }

    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return rtrim(strtolower($url), '/');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim($parts['path'] ?? '', '/');

        return $scheme.'://'.strtolower($parts['host']).$port.$path;
    }

    public static function sameUrl(string $a, string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    public static function host(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    public static function isHostname(mixed $host): bool
    {
        return is_string($host)
            && strlen($host) <= 253
            && preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', $host) === 1;
    }

    public static function isHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && strlen($url) <= 2048
            && str_starts_with($url, 'https://')
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && self::host($url) !== null;
    }

    public static function prefixed(string $hex): string
    {
        return 'sha256:'.$hex;
    }

    public static function unprefixed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $hex = strtolower(str_starts_with($value, 'sha256:') ? substr($value, 7) : $value);

        return preg_match('/^[0-9a-f]{64}$/', $hex) === 1 ? $hex : null;
    }

    public static function userAgent(): string
    {
        return 'LoopsTransit/'.self::VERSION.' (+'.self::url().')';
    }
}
