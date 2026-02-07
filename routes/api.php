<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EmailWebhookController;

Route::post('/chat/send', [ChatController::class, 'sendMessage']);

// Email webhook - receive incoming emails and process with AI
Route::post('/emails/incoming', [EmailWebhookController::class, 'handleIncoming']);
