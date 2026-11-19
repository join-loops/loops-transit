<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransitKey extends Model
{
    public const ACTIVE = 'active';

    public const RETIRED = 'retired';

    protected $fillable = [
        'kid',
        'public_key',
        'secret_key',
        'status',
        'activated_at',
        'retired_at',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'secret_key' => 'encrypted',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function jwk(): array
    {
        return array_filter([
            'kid' => $this->kid,
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'x' => $this->public_key,
            'alg' => 'EdDSA',
            'use' => 'sig',
            'status' => $this->status,
            'created' => $this->activated_at?->toIso8601ZuluString(),
            'retired' => $this->retired_at?->toIso8601ZuluString(),
        ], fn ($value) => $value !== null);
    }
}
