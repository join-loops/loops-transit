<?php

namespace App\Http\Controllers;

use App\Models\AccountMigration;
use App\Transit\Transit;
use App\Transit\TransitException;
use Illuminate\Http\JsonResponse;

class MigrationController extends Controller
{
    public function show(string $hash): JsonResponse
    {
        $hash = Transit::unprefixed($hash);
        $migration = $hash ? AccountMigration::where('intent_hash', $hash)->first() : null;

        if (! $migration) {
            throw new TransitException('This Transit has no record of that intent', 'not_found', 404);
        }

        return response()->json($migration->toPublic());
    }
}
