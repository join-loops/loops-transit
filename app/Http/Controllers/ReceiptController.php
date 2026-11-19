<?php

namespace App\Http\Controllers;

use App\Transit\WitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function store(Request $request, WitnessService $witness): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'max:'.config('transit.max_jws_bytes')],
            'receipt' => ['required', 'string', 'max:'.config('transit.max_jws_bytes')],
        ]);

        [$migration, $created] = $witness->submitReceipt($data['intent'], $data['receipt']);

        return response()->json(['seal' => $migration->seal()], $created ? 201 : 200);
    }
}
