<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BackupRestoreController as AdminBackupRestoreController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ResourceLibraryController as AdminResourceLibraryController;
use App\Http\Controllers\Admin\RolePermissionController as AdminRolePermissionController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\HelpSeekerRegisterController;
use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestSupportController;
use App\Http\Controllers\SelfHelpController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Support\RoleDashboard;
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
// LEGACY SYSTEM ADMINISTRATOR LOGIN URL
// =============================================
// All roles authenticate through /login. Keep the old GET URL only as a
// bookmark-safe redirect; there is no second form or Admin login POST action.
Route::get('/admin/login', fn () => redirect()->route('login'))
    ->middleware('guest')
    ->name('admin.login');

// =============================================
// DASHBOARD (Protected Route)
// Redirects to the user's role-specific dashboard.
// NOTE: `redirect()->intended()` in the login flow and the Breeze
// navigation both point here — it must NEVER return the bare stub view.
// =============================================
Route::get('/dashboard', function () {
    return redirect()->route(RoleDashboard::routeNameFor(auth()->user()));
})->middleware(['auth'])->name('dashboard');

// =============================================
// ROLE-BASED DASHBOARDS
// =============================================
Route::middleware('auth')->group(function () {
    Route::get('/seeker/dashboard', function () {
        return view('dashboard.seeker');
    })->name('seeker.dashboard');

    Route::get('/helper/dashboard', function () {
        return view('dashboard.helper');
    })->name('helper.dashboard');

    Route::get('/adviser/dashboard', function () {
        return view('dashboard.adviser');
    })->name('adviser.dashboard');

    Route::get('/moderator/dashboard', function () {
        return view('dashboard.moderator');
    })->name('moderator.dashboard');

    Route::get('/professional/dashboard', function () {
        return view('dashboard.professional');
    })->name('professional.dashboard');

});

Route::get('/admin/dashboard', AdminDashboardController::class)
    ->middleware(['auth', 'admin'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/roles-permissions', AdminRolePermissionController::class)->name('roles-permissions');
    Route::get('/resource-library', AdminResourceLibraryController::class)->name('resource-library');
    Route::get('/audit-logs', AdminAuditLogController::class)->name('audit-logs');
    Route::get('/backup-restore', AdminBackupRestoreController::class)->name('backup-restore');
    Route::get('/system-health', AdminSystemHealthController::class)->name('system-health');
    Route::get('/reports', AdminReportController::class)->name('reports');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::patch('/settings/preferences', [AdminSettingsController::class, 'updatePreference'])
        ->name('settings.preference.update');
});

// =============================================
// PROFILE ROUTES (profile view + Breeze edit)
// =============================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/view', [ProfileController::class, 'show'])->name('profile.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/delete-account', [ProfileController::class, 'deleteAccount'])->name('profile.delete-account');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =============================================
// BREEZE AUTHENTICATION ROUTES (LOAD FIRST)
// =============================================
require __DIR__.'/auth.php';

// =============================================
// CUSTOM HELP SEEKER REGISTRATION (WITH OTP)
// MUST BE AFTER require __DIR__.'/auth.php' TO OVERRIDE
// =============================================
Route::get('/register', [HelpSeekerRegisterController::class, 'showRegisterForm'])->name('register');
Route::get('/register-seeker', [HelpSeekerRegisterController::class, 'showRegisterForm'])->name('seeker.register');

// =============================================
// REQUEST SUPPORT ROUTES (3-Step Process)
// =============================================
Route::middleware(['auth'])->group(function () {
    // Step 1: Screening (Area of Concern, Description, Urgency, Safety Check)
    Route::get('/request/screening', [RequestSupportController::class, 'screening'])->name('request.screening');
    Route::post('/request/screening', [RequestSupportController::class, 'processScreening'])->name('request.screening.process');

    // Step 2: Preferences (Support Mode, Language, Notes)
    Route::get('/request/preferences', [RequestSupportController::class, 'preferences'])->name('request.preferences');
    Route::post('/request/preferences', [RequestSupportController::class, 'processPreferences'])->name('request.preferences.process');

    // Step 3: Matching / Queue
    Route::get('/request/matching', [RequestSupportController::class, 'matching'])->name('request.matching');
    Route::post('/request/matching', [RequestSupportController::class, 'declineHelper'])->name('request.matching.decline');

    // Voice Recording Consent
    Route::get('/request/voice-consent', [RequestSupportController::class, 'voiceConsent'])->name('request.voice-consent');
    Route::post('/request/voice-consent', [RequestSupportController::class, 'processVoiceConsent'])->name('request.voice-consent.process');
    Route::post('/request/voice-consent/decline', [RequestSupportController::class, 'declineVoiceConsent'])->name('request.voice-consent.decline');

    // Session Routes (Chat / Voice / Evaluation)
    Route::get('/session/chat', [SessionController::class, 'chat'])->name('session.chat');
    Route::post('/session/chat', [SessionController::class, 'sendMessage'])->name('session.chat.send');
    Route::get('/session/voice', [SessionController::class, 'voice'])->name('session.voice');
    Route::post('/session/end', [SessionController::class, 'endSession'])->name('session.end');
    Route::get('/session/evaluation', [SessionController::class, 'evaluation'])->name('session.evaluation');
    Route::post('/session/evaluation', [SessionController::class, 'processEvaluation'])->name('session.evaluation.process');
    Route::get('/session/history', [SessionController::class, 'history'])->name('session.history');

    // Additional Seeker Pages
    Route::get('/emergency', function () {
        return view('emergency');
    })->name('emergency');
});

// =============================================
// SELF-HELP TOOLS
// =============================================
Route::middleware(['auth'])->group(function () {
    Route::get('/selfhelp', [SelfHelpController::class, 'index'])->name('selfhelp');
    Route::get('/selfhelp/category/{category}', [SelfHelpController::class, 'category'])->name('selfhelp.category');
    Route::get('/selfhelp/resource/{id}', [SelfHelpController::class, 'show'])->name('selfhelp.show');
    Route::post('/selfhelp/resource/{id}/save', [SelfHelpController::class, 'save'])->name('selfhelp.save');
    Route::post('/selfhelp/resource/{id}/unsave', [SelfHelpController::class, 'unsave'])->name('selfhelp.unsave');
    Route::post('/selfhelp/resource/{id}/progress', [SelfHelpController::class, 'updateProgress'])->name('selfhelp.progress');
    Route::post('/selfhelp/resource/{id}/complete', [SelfHelpController::class, 'complete'])->name('selfhelp.complete');
});

// =============================================
// NOTIFICATIONS
// =============================================
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
});

// =============================================
// SETTINGS
// =============================================
Route::middleware(['auth'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/settings/account', [SettingsController::class, 'account'])->name('settings.account');
    Route::get('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
    Route::get('/settings/privacy', [SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::get('/settings/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::get('/settings/export-data', [SettingsController::class, 'downloadData'])->name('settings.export-data');
    Route::patch('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
    Route::patch('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::patch('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy.update');
    Route::patch('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
});

// =============================================
// API ROUTES (for AJAX calls)
// =============================================
Route::prefix('api')->group(function () {
    // OTP Routes
    Route::post('/send-otp', [OTPController::class, 'sendOTP']);
    Route::post('/verify-otp', [OTPController::class, 'verifyOTP']);
    Route::post('/resend-otp', [OTPController::class, 'resendOTP']);
    Route::post('/check-email', [OTPController::class, 'checkEmail']);

    // Help Seeker Registration
    Route::get('/generate-alias', [HelpSeekerRegisterController::class, 'generateAlias']);
    Route::post('/register-seeker', [HelpSeekerRegisterController::class, 'register']);
});
