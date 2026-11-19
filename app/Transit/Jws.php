<?php

namespace App\Transit;

final class Jws
{
    public function __construct(
        public readonly array $header,
        public readonly array $payload,
        public readonly string $signingInput,
        public readonly string $signature,
        public readonly string $compact,
    ) {}

    public static function parse(string $compact, ?int $maxBytes = null): self
    {
        if (strlen($compact) > ($maxBytes ?? (int) config('transit.max_jws_bytes'))) {
            throw new TransitException('The attestation is too large', 'too_large', 413);
        }

        $parts = explode('.', $compact);

        if (count($parts) !== 3) {
            throw new TransitException('The attestation is not a compact JWS', 'malformed');
        }

        $header = self::object(Json::unb64($parts[0]));
        $payload = self::object(Json::unb64($parts[1]));
        $signature = Json::unb64($parts[2]);

        if ($header === null || $payload === null || $signature === null || $signature === '') {
            throw new TransitException('The attestation is not a compact JWS', 'malformed');
        }

        return new self($header, $payload, $parts[0].'.'.$parts[1], $signature, $compact);
    }

    public static function signEdDSA(array $header, array $payload, string $secretKey): string
    {
        $input = Json::b64(Json::encode($header)).'.'.Json::b64(Json::encode($payload));

        return $input.'.'.Json::b64(sodium_crypto_sign_detached($input, $secretKey));
    }

    public static function payloadOf(string $compact): array
    {
        return self::object(Json::unb64(explode('.', $compact)[1] ?? '')) ?? [];
    }

    public static function headerOf(string $compact): array
    {
        return self::object(Json::unb64(explode('.', $compact)[0])) ?? [];
    }

    public function hash(): string
    {
        return hash('sha256', $this->compact);
    }

    private static function object(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($value) && $value !== [] && ! array_is_list($value) ? $value : null;
    }
}
