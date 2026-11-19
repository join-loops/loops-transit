<?php

namespace App\Transit;

use Carbon\CarbonImmutable;

class AttestationRules
{
    public const INTENT_TYPE = 'MigrationIntent';

    public const RECEIPT_TYPE = 'MigrationReceipt';

    public function intent(Jws $jws): array
    {
        $payload = $jws->payload;

        $this->require(
            ($payload['type'] ?? null) === self::INTENT_TYPE && ($payload['version'] ?? null) === Transit::PROTOCOL_VERSION,
            'The attestation is not a version 1 migration intent',
            'wrong_type'
        );

        foreach (['id', 'subject', 'target'] as $field) {
            $this->require(Transit::isHttpsUrl($payload[$field] ?? null), 'The intent '.$field.' must be an https URL', 'invalid_intent');
        }

        $source = $payload['source'] ?? null;
        $destination = $payload['destination'] ?? null;

        $this->require(Transit::isHostname($source) && Transit::isHostname($destination), 'The intent hosts are invalid', 'invalid_intent');
        $this->require($source !== $destination, 'The source and destination must be different servers', 'same_server');
        $this->require(
            Transit::host($payload['id']) === $source && Transit::host($payload['subject']) === $source && Transit::host($payload['target']) === $destination,
            'The intent accounts do not match its servers',
            'invalid_intent'
        );

        $manifestRoot = Transit::unprefixed(data_get($payload, 'manifest.root'));

        $this->require($manifestRoot !== null, 'The intent manifest root is invalid', 'invalid_intent');
        $this->require(
            is_string($payload['transit'] ?? null) && Transit::sameUrl($payload['transit'], Transit::url()),
            'The intent names a different Transit',
            'wrong_transit'
        );

        $issued = $this->time($payload['issued'] ?? null);
        $expires = $this->time($payload['expires'] ?? null);

        $this->require($issued !== null && $expires !== null, 'The intent timestamps are invalid', 'invalid_intent');
        $this->require($issued->lte(now()->addSeconds($this->skew())), 'The intent was issued in the future', 'clock_skew');
        $this->require(
            $expires->gt($issued) && $expires->lte($issued->addDays((int) config('transit.max_intent_days'))),
            'The intent lifetime is not allowed',
            'invalid_intent'
        );

        return [
            'id' => $payload['id'],
            'subject' => $payload['subject'],
            'target' => $payload['target'],
            'source' => $source,
            'destination' => $destination,
            'manifestRoot' => $manifestRoot,
            'issued' => $issued,
            'expires' => $expires,
        ];
    }

    public function assertNotExpired(array $intent): void
    {
        $this->require($intent['expires']->gt(now()->subSeconds($this->skew())), 'The intent has expired', 'expired');
    }

    public function receipt(Jws $receipt, Jws $intentJws, array $intent): array
    {
        $payload = $receipt->payload;

        $this->require(
            ($payload['type'] ?? null) === self::RECEIPT_TYPE && ($payload['version'] ?? null) === Transit::PROTOCOL_VERSION,
            'The attestation is not a version 1 migration receipt',
            'wrong_type'
        );

        $intentHash = Transit::unprefixed($payload['intentHash'] ?? null);

        $this->require(($payload['intent'] ?? null) === $intent['id'], 'The receipt is for a different intent', 'receipt_mismatch');
        $this->require($intentHash !== null && hash_equals($intentJws->hash(), $intentHash), 'The receipt intent hash does not match', 'receipt_mismatch');

        foreach (['subject', 'target', 'source', 'destination'] as $field) {
            $this->require(($payload[$field] ?? null) === $intent[$field], 'The receipt '.$field.' does not match the intent', 'receipt_mismatch');
        }

        $this->require(
            Transit::unprefixed($payload['manifestRoot'] ?? null) === $intent['manifestRoot'],
            'The receipt manifest root does not match the intent',
            'receipt_mismatch'
        );

        $issued = $this->time($payload['issued'] ?? null);

        $this->require($issued !== null, 'The receipt timestamp is invalid', 'invalid_receipt');
        $this->require($issued->lte(now()->addSeconds($this->skew())), 'The receipt was issued in the future', 'clock_skew');
        $this->require($issued->gte($intent['issued']->subSeconds($this->skew())), 'The receipt predates the intent', 'invalid_receipt');

        return [
            'mapRoot' => Transit::unprefixed(data_get($payload, 'map.root')),
            'issued' => $issued,
        ];
    }

    protected function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function skew(): int
    {
        return max(0, (int) config('transit.clock_skew'));
    }

    protected function require(bool $condition, string $message, string $reason): void
    {
        if (! $condition) {
            throw new TransitException($message, $reason);
        }
    }
}
