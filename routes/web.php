<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/lookup', [LookupController::class, 'show'])->name('lookup');
Route::post('/lookup', [LookupController::class, 'search'])->middleware('throttle:30,1')->name('lookup.search');
