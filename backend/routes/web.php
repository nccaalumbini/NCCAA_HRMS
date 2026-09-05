<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/apply/{skill?}', function (?string $skill = null) {
    return view('portal', ['skill' => $skill]);
})->where('skill', '[a-z0-9-]+');
