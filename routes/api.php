<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Middleware\IsLoggedIn;

Route::post('/chat/send', [ChatController::class, 'sendMessage'])->middleware(IsLoggedIn::class);
