<?php

use Illuminate\Support\Facades\Route;

// The app UI is the separate Vue PWA (frontend/); this backend only serves the JSON API.
Route::get('/', fn () => response()->json(['name' => config('app.name'), 'api' => url('/api')]));
