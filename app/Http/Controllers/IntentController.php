<?php

namespace App\Http\Controllers;

use App\Transit\WitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntentController extends Controller
{
    public function store(Request $request, WitnessService $witness): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'max:'.config('transit.max_jws_bytes')],
        ]);

        [$migration, $created] = $witness->submitIntent($data['intent']);

        return response()->json(['entry' => $migration->entry()], $created ? 201 : 200);
    }
}
