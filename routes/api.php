<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::post('/chat/send', [ChatController::class, 'sendMessage']);
Route::post('/chat/stream', [ChatController::class, 'streamMessage']);
