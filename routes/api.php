<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiController;

Route::get('/tes2t', function (Request $request) {
    return response()->stream(function (): Generator {
        foreach (['developer', 'admin'] as $string) {
            sleep(2); // Simulate delay between chunks...
            yield $string . "\n";
        }
    }, 200, ['X-Accel-Buffering' => 'no']);
});

Route::get('/test', [AiController::class, 'index']);
