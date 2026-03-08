<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiController;
use App\Http\Controllers\VoiceController;

Route::post('/test', [AiController::class, 'index']);

Route::post('/voice/sessions', [VoiceController::class, 'createSession']);
Route::put('/voice/sessions/{id}/end', [VoiceController::class, 'endSession']);
Route::post('/voice/transcripts', [VoiceController::class, 'addTranscript']);
Route::post('/voice/commands', [VoiceController::class, 'addCommand']);
Route::put('/voice/commands/complete', [VoiceController::class, 'completeCommand']);
