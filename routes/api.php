<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\IsLoggedIn;

Route::post('/chat/send', [ChatController::class, 'sendMessage'])->middleware(IsLoggedIn::class);
Route::post('webhooks/{service}', [WebhookController::class, 'handle']);
