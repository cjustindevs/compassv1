<?php

use App\Http\Controllers\Auth\HelpSeekerRegisterController;
use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// =============================================
// LANDING PAGE
// =============================================
Route::get('/', [LandingPageController::class, 'index'])->name('home');

// =============================================
// DASHBOARD (Protected)
// =============================================
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// =============================================
// PROFILE ROUTES (Breeze)
// =============================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =============================================
// BREEZE AUTHENTICATION ROUTES
// (Login, Logout, Password Reset, Email Verification)
// =============================================
require __DIR__.'/auth.php';

// =============================================
// OVERRIDE BREEZE'S REGISTER WITH CUSTOM OTP REGISTRATION
// MUST BE AFTER require __DIR__.'/auth.php' TO OVERRIDE
// =============================================
Route::get('/register', [HelpSeekerRegisterController::class, 'showRegisterForm'])->name('register');
Route::get('/register-seeker', [HelpSeekerRegisterController::class, 'showRegisterForm'])->name('seeker.register');

// =============================================
// API ROUTES (AJAX calls)
// =============================================
Route::prefix('api')->group(function () {
    Route::post('/send-otp', [OTPController::class, 'sendOTP']);
    Route::post('/verify-otp', [OTPController::class, 'verifyOTP']);
    Route::post('/resend-otp', [OTPController::class, 'resendOTP']);
    Route::post('/check-email', [OTPController::class, 'checkEmail']);
    Route::get('/generate-alias', [HelpSeekerRegisterController::class, 'generateAlias']);
    Route::post('/register-seeker', [HelpSeekerRegisterController::class, 'register']);
});