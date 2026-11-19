<?php

namespace App\Models;

use App\Transit\Transit;
use Illuminate\Database\Eloquent\Model;

class LogLeaf extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'leaf_index';

    protected $keyType = 'int';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'leaf_index' => 'integer',
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function toPublic(): array
    {
        return [
            'index' => $this->leaf_index,
            'kind' => $this->kind,
            'hash' => Transit::prefixed($this->leaf_hash),
            'data' => $this->data,
        ];
    }
}
