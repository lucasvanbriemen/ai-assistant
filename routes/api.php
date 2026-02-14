<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\IsLoggedIn;

// Chat endpoint
Route::post('/chat/send', [ChatController::class, 'sendMessage'])->middleware(IsLoggedIn::class);

Route::prefix('webhooks')->group(function () {
    // Generic webhook handler
    Route::post('/{service}', [WebhookController::class, 'handle']);

    // Stats endpoint (monitoring)
    Route::get('/stats', [WebhookController::class, 'stats']);

    // Health check endpoint
    Route::get('/health', [WebhookController::class, 'health']);
});
