<?php

use App\Http\Controllers\IntentController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\WellKnownController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/loops-transit', WellKnownController::class)->name('well-known');

Route::prefix('api/v1')->group(function () {
    Route::post('intents', [IntentController::class, 'store'])->middleware('throttle:transit-submit')->name('intents.store');
    Route::post('receipts', [ReceiptController::class, 'store'])->middleware('throttle:transit-submit')->name('receipts.store');

    Route::get('log', [LogController::class, 'head'])->name('log.head');
    Route::get('log/entries', [LogController::class, 'entries'])->name('log.entries');
    Route::get('log/proof/inclusion', [LogController::class, 'inclusion'])->name('log.inclusion');
    Route::get('log/proof/consistency', [LogController::class, 'consistency'])->name('log.consistency');

    Route::get('migrations/{hash}', [MigrationController::class, 'show'])->name('migrations.show');
});
