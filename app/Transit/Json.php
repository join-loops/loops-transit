<?php

namespace App\Transit;

final class Json
{
    public const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public static function encode(array $value): string
    {
        return json_encode($value, self::FLAGS);
    }

    public static function canonical(array $value): string
    {
        return json_encode(self::sort($value), self::FLAGS);
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function unb64(string $data): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $data) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($item) => self::sort($item), $value);
    }
}
