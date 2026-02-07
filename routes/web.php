<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all route (must be last)
Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*')->name('spa.catchall');
