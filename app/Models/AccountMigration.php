<?php

namespace App\Models;

use App\Transit\Jws;
use App\Transit\Transit;
use Illuminate\Database\Eloquent\Model;

class AccountMigration extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'intent_jws',
        'receipt_jws',
    ];

    protected function casts(): array
    {
        return [
            'intent_leaf' => 'integer',
            'seal_leaf' => 'integer',
            'receipts_count' => 'integer',
            'intent_received_at' => 'datetime',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'sealed_at' => 'datetime',
        ];
    }

    public function isSealed(): bool
    {
        return $this->seal_jws !== null;
    }

    public function entry(): ?array
    {
        if ($this->entry_jws === null) {
            return null;
        }

        $log = (array) (Jws::payloadOf($this->entry_jws)['log'] ?? []);

        return [
            'jws' => $this->entry_jws,
            'index' => $this->intent_leaf,
            'size' => $log['size'] ?? null,
            'root' => $log['root'] ?? null,
            'intentHash' => Transit::prefixed($this->intent_hash),
            'received' => $this->intent_received_at?->toIso8601ZuluString(),
        ];
    }

    public function seal(): ?array
    {
        if ($this->seal_jws === null) {
            return null;
        }

        $log = (array) (Jws::payloadOf($this->seal_jws)['log'] ?? []);

        return [
            'jws' => $this->seal_jws,
            'kid' => Jws::headerOf($this->seal_jws)['kid'] ?? null,
            'index' => $this->seal_leaf,
            'size' => $log['size'] ?? null,
            'root' => $log['root'] ?? null,
            'receiptHash' => Transit::prefixed((string) $this->receipt_hash),
            'sealed' => $this->sealed_at?->toIso8601ZuluString(),
        ];
    }

    public function toPublic(): array
    {
        return [
            'intentHash' => Transit::prefixed($this->intent_hash),
            'source' => $this->source_host,
            'destination' => $this->destination_host,
            'keys' => array_filter([
                'source' => $this->intent_key_fingerprint,
                'destination' => $this->receipt_key_fingerprint,
            ]),
            'expires' => $this->expires_at?->toIso8601ZuluString(),
            'entry' => $this->entry(),
            'seal' => $this->seal(),
            'receipts' => $this->receipts_count,
        ];
    }
}
