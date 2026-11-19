<?php

namespace App\Models;

use App\Transit\Jws;
use App\Transit\Transit;
use Illuminate\Database\Eloquent\Model;

class TreeHead extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'size';

    protected $keyType = 'int';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function toPublic(): array
    {
        return [
            'size' => $this->size,
            'root' => Transit::prefixed($this->root),
            'timestamp' => Jws::payloadOf($this->jws)['timestamp'] ?? $this->created_at?->toIso8601ZuluString(),
            'jws' => $this->jws,
        ];
    }
}
