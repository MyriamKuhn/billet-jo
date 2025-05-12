<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/backend', function () {
    return view('backend');
});

Route::get('/docs/frontend', function () {
    return view('frontend');
});
