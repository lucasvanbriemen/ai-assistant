<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiController;
use App\Http\Controllers\PrimeController;

Route::post('/test', [AiController::class, 'index']);
Route::post('/prime/chat', [PrimeController::class, 'chat']);
