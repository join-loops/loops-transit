<?php

namespace App\Transit;

use Illuminate\Support\Facades\Cache;

class AttestationVerifier
{
    public const JWS_TYPE = 'loops-migration+jws';

    public const ACCEPT = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';

    public function __construct(protected RemoteFetcher $fetcher) {}

    public function verify(Jws $jws, string $expectedHost): string
    {
        if (($jws->header['alg'] ?? null) !== 'RS256' || ($jws->header['typ'] ?? null) !== self::JWS_TYPE) {
            throw new TransitException('Unsupported attestation format', 'unsupported_format');
        }

        $kid = $jws->header['kid'] ?? null;

        if (! Transit::isHttpsUrl($kid) || Transit::host($kid) !== $expectedHost) {
            throw new TransitException('The attestation key does not belong to '.$expectedHost, 'key_mismatch');
        }

        foreach ([false, true] as $fresh) {
            $pem = $this->publicKey($kid, $fresh);

            if ($pem !== null && openssl_verify($jws->signingInput, $jws->signature, $pem, OPENSSL_ALGO_SHA256) === 1) {
                return $this->fingerprint($pem);
            }
        }

        throw new TransitException('The attestation signature is invalid', 'bad_signature');
    }

    public function publicKey(string $kid, bool $fresh = false): ?string
    {
        $cacheKey = 'transit:actor-key:'.sha1($kid);

        if (! $fresh && is_string($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $owner = explode('#', $kid, 2)[0];

        try {
            $document = $this->fetcher->json($owner, self::ACCEPT);
        } catch (TransitException) {
            return null;
        }

        if (! is_array($document) || ($document['id'] ?? null) !== $owner) {
            return null;
        }

        $key = $document['publicKey'] ?? null;

        if (is_array($key) && array_is_list($key)) {
            $key = collect($key)->first(fn ($candidate) => is_array($candidate) && ($candidate['id'] ?? null) === $kid);
        }

        if (! is_array($key) || ($key['id'] ?? null) !== $kid || ! is_string($key['publicKeyPem'] ?? null)) {
            return null;
        }

        $resource = openssl_pkey_get_public($key['publicKeyPem']);
        $details = $resource ? openssl_pkey_get_details($resource) : false;

        if (! is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
            return null;
        }

        Cache::put($cacheKey, $key['publicKeyPem'], now()->addHour());

        return $key['publicKeyPem'];
    }

    public function fingerprint(string $pem): string
    {
        $der = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem), true);

        return Transit::prefixed(hash('sha256', $der === false ? $pem : $der));
    }
}
