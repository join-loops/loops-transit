<?php

namespace App\Transit;

use App\Models\TransitKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KeyRing
{
    public const JWS_TYPE = 'loops-transit+jws';

    public function active(): TransitKey
    {
        $key = TransitKey::where('status', TransitKey::ACTIVE)->orderByDesc('id')->first();

        if (! $key) {
            throw new TransitException('This Transit has no signing key yet', 'not_configured', 503);
        }

        return $key;
    }

    public function hasActive(): bool
    {
        return TransitKey::where('status', TransitKey::ACTIVE)->exists();
    }

    public function all(): Collection
    {
        return TransitKey::orderByDesc('id')->get();
    }

    public function generate(): TransitKey
    {
        $pair = sodium_crypto_sign_keypair();
        $public = Json::b64(sodium_crypto_sign_publickey($pair));
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));

        return DB::transaction(function () use ($public, $secret) {
            TransitKey::where('status', TransitKey::ACTIVE)->update([
                'status' => TransitKey::RETIRED,
                'secret_key' => null,
                'retired_at' => now(),
            ]);

            return TransitKey::create([
                'kid' => $this->thumbprint($public),
                'public_key' => $public,
                'secret_key' => $secret,
                'status' => TransitKey::ACTIVE,
                'activated_at' => now(),
            ]);
        });
    }

    public function thumbprint(string $x): string
    {
        return Json::b64(hash('sha256', '{"crv":"Ed25519","kty":"OKP","x":"'.$x.'"}', true));
    }

    public function sign(array $payload): string
    {
        $key = $this->active();

        return Jws::signEdDSA(
            ['alg' => 'EdDSA', 'kid' => $key->kid, 'typ' => self::JWS_TYPE],
            $payload,
            base64_decode((string) $key->secret_key, true) ?: ''
        );
    }

    public function verify(string $compact): ?array
    {
        try {
            $jws = Jws::parse($compact, 65536);
        } catch (TransitException) {
            return null;
        }

        if (($jws->header['alg'] ?? null) !== 'EdDSA' || strlen($jws->signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        $key = TransitKey::where('kid', (string) ($jws->header['kid'] ?? ''))->first();
        $public = $key ? Json::unb64($key->public_key) : null;

        if (! is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        return sodium_crypto_sign_verify_detached($jws->signature, $jws->signingInput, $public) ? $jws->payload : null;
    }
}
