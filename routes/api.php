<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function (Request $request) {
    return response()->stream(function (): Generator {
        foreach (['developer', 'admin'] as $string) {
            sleep(2); // Simulate delay between chunks...
            yield $string . "\n";
        }
    }, 200, ['X-Accel-Buffering' => 'no']);
});
