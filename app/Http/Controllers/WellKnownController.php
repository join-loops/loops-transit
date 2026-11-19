<?php

namespace App\Http\Controllers;

use App\Models\TransitKey;
use App\Transit\KeyRing;
use App\Transit\Log\MerkleLog;
use App\Transit\Transit;
use Illuminate\Http\JsonResponse;

class WellKnownController extends Controller
{
    public function __invoke(KeyRing $keys, MerkleLog $log): JsonResponse
    {
        $state = $log->state();

        return response()->json([
            'version' => Transit::PROTOCOL_VERSION,
            'software' => [
                'name' => 'loops-transit',
                'version' => Transit::VERSION,
            ],
            'transit' => Transit::url(),
            'operator' => array_filter((array) config('transit.operator')),
            'keys' => $keys->all()->map(fn (TransitKey $key) => $key->jwk())->values(),
            'endpoints' => [
                'intents' => Transit::endpoint('api/v1/intents'),
                'receipts' => Transit::endpoint('api/v1/receipts'),
                'log' => Transit::endpoint('api/v1/log'),
                'migrations' => Transit::endpoint('api/v1/migrations'),
            ],
            'log' => [
                'size' => $state['size'],
                'root' => Transit::prefixed($state['root']),
            ],
            'policy' => [
                'requireAdvertised' => (bool) config('transit.require_advertised'),
                'restricted' => config('transit.allowed_hosts') !== [],
                'maxIntentDays' => (int) config('transit.max_intent_days'),
                'maxReceipts' => (int) config('transit.max_receipts'),
            ],
        ], 200, ['Cache-Control' => 'public, max-age=60']);
    }
}
