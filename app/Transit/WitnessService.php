<?php

namespace App\Transit;

use App\Models\AccountMigration;
use App\Transit\Log\MerkleLog;

class WitnessService
{
    public const ENTRY_TYPE = 'TransitEntry';

    public const SEAL_TYPE = 'TransitSeal';

    public function __construct(
        protected AttestationRules $rules,
        protected AttestationVerifier $verifier,
        protected HostPolicy $policy,
        protected MerkleLog $log,
        protected KeyRing $keys,
    ) {}

    public function submitIntent(string $intentCompact): array
    {
        $this->keys->active();

        $jws = Jws::parse($intentCompact);
        $intent = $this->rules->intent($jws);
        $this->rules->assertNotExpired($intent);
        $hash = $jws->hash();

        $existing = AccountMigration::where('intent_hash', $hash)->first();

        if ($existing?->entry_jws !== null) {
            return [$existing, false];
        }

        $this->policy->admit($intent['source'], [$intent['source'], $intent['destination']]);

        $sourceKey = $this->verifier->verify($jws, $intent['source']);

        return $this->log->locked(function () use ($intentCompact, $intent, $hash, $sourceKey) {
            $migration = AccountMigration::where('intent_hash', $hash)->first();

            if ($migration?->entry_jws !== null) {
                return [$migration, false];
            }

            $now = now();
            $migration ??= $this->newMigration($intentCompact, $intent, $hash, $sourceKey);

            $entry = $this->log->append('intent', [
                'kind' => 'intent',
                'intentHash' => Transit::prefixed($hash),
                'source' => $intent['source'],
                'destination' => $intent['destination'],
                'sourceKey' => $migration->intent_key_fingerprint,
                'received' => $now->toIso8601ZuluString(),
            ]);

            $migration->fill([
                'intent_leaf' => $entry['index'],
                'intent_received_at' => $now,
                'entry_jws' => $this->keys->sign([
                    'type' => self::ENTRY_TYPE,
                    'version' => Transit::PROTOCOL_VERSION,
                    'transit' => Transit::url(),
                    'intent' => $intent['id'],
                    'intentHash' => Transit::prefixed($hash),
                    'source' => $intent['source'],
                    'destination' => $intent['destination'],
                    'sourceKey' => $migration->intent_key_fingerprint,
                    'log' => $this->logReference($entry),
                    'received' => $now->toIso8601ZuluString(),
                ]),
            ])->save();

            return [$migration, true];
        });
    }

    public function submitReceipt(string $intentCompact, string $receiptCompact): array
    {
        $this->keys->active();

        $intentJws = Jws::parse($intentCompact);
        $receiptJws = Jws::parse($receiptCompact);
        $intent = $this->rules->intent($intentJws);
        $receipt = $this->rules->receipt($receiptJws, $intentJws, $intent);
        $intentHash = $intentJws->hash();
        $receiptHash = $receiptJws->hash();

        $existing = AccountMigration::where('intent_hash', $intentHash)->first();

        if ($existing && $existing->isSealed() && $existing->receipt_hash === $receiptHash) {
            return [$existing, false];
        }

        $this->assertReceiptAllowed($existing);
        $this->policy->admit($intent['destination'], [$intent['source'], $intent['destination']]);

        $sourceKey = $existing?->intent_key_fingerprint ?? $this->verifier->verify($intentJws, $intent['source']);
        $destinationKey = $this->verifier->verify($receiptJws, $intent['destination']);

        return $this->log->locked(function () use ($intentCompact, $receiptCompact, $intent, $receipt, $intentHash, $receiptHash, $sourceKey, $destinationKey) {
            $migration = AccountMigration::where('intent_hash', $intentHash)->first();

            if ($migration && $migration->isSealed() && $migration->receipt_hash === $receiptHash) {
                return [$migration, false];
            }

            $this->assertReceiptAllowed($migration);

            $now = now();
            $migration ??= $this->newMigration($intentCompact, $intent, $intentHash, $sourceKey);

            $entry = $this->log->append('seal', array_filter([
                'kind' => 'seal',
                'intentHash' => Transit::prefixed($intentHash),
                'receiptHash' => Transit::prefixed($receiptHash),
                'source' => $intent['source'],
                'destination' => $intent['destination'],
                'sourceKey' => $migration->intent_key_fingerprint,
                'destinationKey' => $destinationKey,
                'manifestRoot' => Transit::prefixed($intent['manifestRoot']),
                'mapRoot' => $receipt['mapRoot'] ? Transit::prefixed($receipt['mapRoot']) : null,
                'sealed' => $now->toIso8601ZuluString(),
            ], fn ($value) => $value !== null));

            $seal = $this->keys->sign(array_filter([
                'type' => self::SEAL_TYPE,
                'version' => Transit::PROTOCOL_VERSION,
                'transit' => Transit::url(),
                'intent' => $intent['id'],
                'intentHash' => Transit::prefixed($intentHash),
                'receiptHash' => Transit::prefixed($receiptHash),
                'subject' => $intent['subject'],
                'target' => $intent['target'],
                'source' => $intent['source'],
                'destination' => $intent['destination'],
                'manifestRoot' => Transit::prefixed($intent['manifestRoot']),
                'mapRoot' => $receipt['mapRoot'] ? Transit::prefixed($receipt['mapRoot']) : null,
                'keys' => [
                    'source' => $migration->intent_key_fingerprint,
                    'destination' => $destinationKey,
                ],
                'entry' => $migration->intent_leaf,
                'log' => $this->logReference($entry),
                'issued' => $now->toIso8601ZuluString(),
            ], fn ($value) => $value !== null));

            $migration->fill([
                'receipt_hash' => $receiptHash,
                'receipt_jws' => $receiptCompact,
                'receipt_key_fingerprint' => $destinationKey,
                'seal_jws' => $seal,
                'seal_leaf' => $entry['index'],
                'sealed_at' => $now,
                'receipts_count' => (int) $migration->receipts_count + 1,
            ])->save();

            return [$migration, true];
        });
    }

    protected function newMigration(string $intentCompact, array $intent, string $hash, string $sourceKey): AccountMigration
    {
        return new AccountMigration([
            'intent_hash' => $hash,
            'intent_id' => $intent['id'],
            'source_host' => $intent['source'],
            'destination_host' => $intent['destination'],
            'intent_jws' => $intentCompact,
            'intent_key_fingerprint' => $sourceKey,
            'issued_at' => $intent['issued'],
            'expires_at' => $intent['expires'],
        ]);
    }

    protected function assertReceiptAllowed(?AccountMigration $migration): void
    {
        if ($migration && $migration->receipts_count >= max(1, (int) config('transit.max_receipts'))) {
            throw new TransitException('This migration has reached the receipt limit', 'too_many_receipts', 409);
        }
    }

    protected function logReference(array $entry): array
    {
        return [
            'index' => $entry['index'],
            'size' => $entry['size'],
            'root' => Transit::prefixed($entry['root']),
        ];
    }
}
