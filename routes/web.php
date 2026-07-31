<?php

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

// Landing Page
Route::get('/', [LandingPageController::class, 'index'])->name('home');

// Authentication Routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

// Dashboard (protected)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// Auth routes (Breeze)
require __DIR__.'/auth.php';