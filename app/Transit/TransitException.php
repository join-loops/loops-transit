<?php

namespace App\Transit;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class TransitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'invalid',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->reason,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
