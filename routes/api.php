<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::post('/chat/stream', [ChatController::class, 'streamMessage']);
