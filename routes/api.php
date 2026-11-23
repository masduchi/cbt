<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('auth/token', [AuthController::class, 'token'])->middleware('throttle:10,1');

Route::middleware('api.token')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('sync', SyncController::class)->middleware('throttle:60,1');

    Route::get('profil', [ProfileController::class, 'show']);
    Route::put('profil', [ProfileController::class, 'update']);
});
