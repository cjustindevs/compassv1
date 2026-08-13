<?php

use App\Http\Controllers\Auth\HelpSeekerRegisterController;
use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\Helper\HelperCalendarController;
use App\Http\Controllers\Helper\HelperCaseController;
use App\Http\Controllers\Helper\HelperChatController;
use App\Http\Controllers\Helper\HelperCompetencyController;
use App\Http\Controllers\Helper\HelperDashboardController;
use App\Http\Controllers\Helper\HelperNotificationController;
use App\Http\Controllers\Helper\HelperNotesController;
use App\Http\Controllers\Helper\HelperProfileController;
use App\Http\Controllers\Helper\HelperReadinessController;
use App\Http\Controllers\Helper\HelperResourceController;
use App\Http\Controllers\Helper\HelperSessionController;
use App\Http\Controllers\Helper\HelperSettingsController;
use App\Http\Controllers\Helper\HelperVoiceController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestSupportController;
use App\Http\Controllers\SelfHelpController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
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
// DASHBOARD (Protected Route)
// Redirects to the user's role-specific dashboard.
// =============================================
Route::get('/dashboard', function () {
    $role = auth()->user()?->role ?? 'seeker';

    $route = match ($role) {
        'admin' => 'admin.dashboard',
        'adviser' => 'adviser.dashboard',
        'helper' => 'helper.dashboard',
        'moderator' => 'moderator.dashboard',
        'professional' => 'professional.dashboard',
        default => 'seeker.dashboard',
    };

    return redirect()->route($route);
})->middleware(['auth'])->name('dashboard');

// =============================================
// ROLE-BASED DASHBOARDS
// =============================================
Route::middleware('auth')->group(function () {
    Route::get('/seeker/dashboard', function () {
        return view('dashboard.seeker');
    })->name('seeker.dashboard');

    Route::get('/helper/dashboard', [HelperDashboardController::class, 'index'])->name('helper.dashboard');

    Route::get('/adviser/dashboard', function () {
        return view('dashboard.adviser');
    })->name('adviser.dashboard');

    Route::get('/moderator/dashboard', function () {
        return view('dashboard.moderator');
    })->name('moderator.dashboard');

    Route::get('/professional/dashboard', function () {
        return view('dashboard.professional');
    })->name('professional.dashboard');

    Route::get('/admin/dashboard', function () {
        return view('dashboard.admin');
    })->name('admin.dashboard');
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
// HELPER MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:helper'])->prefix('helper')->name('helper.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [HelperDashboardController::class, 'index'])->name('dashboard');

    // Readiness Check
    Route::get('/readiness', [HelperReadinessController::class, 'index'])->name('readiness');
    Route::post('/readiness', [HelperReadinessController::class, 'store'])->name('readiness.store');
    Route::get('/readiness/history', [HelperReadinessController::class, 'history'])->name('readiness.history');

    // Assigned Cases
    Route::get('/cases', [HelperCaseController::class, 'index'])->name('cases');
    Route::get('/cases/{id}', [HelperCaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{id}/accept', [HelperCaseController::class, 'accept'])->name('cases.accept');
    Route::post('/cases/{id}/decline', [HelperCaseController::class, 'decline'])->name('cases.decline');

    // Session Management (Chat / Voice / Notes)
    Route::get('/session/{id}/chat', [HelperSessionController::class, 'chat'])->name('session.chat');
    Route::get('/session/{id}/chat/messages', [HelperChatController::class, 'messages'])->name('session.chat.messages');
    Route::post('/session/{id}/chat/send', [HelperSessionController::class, 'sendMessage'])->name('session.chat.send');
    Route::get('/session/{id}/voice', [HelperSessionController::class, 'voice'])->name('session.voice');
    Route::post('/session/{id}/voice/start', [HelperSessionController::class, 'startVoice'])->name('session.voice.start');
    Route::post('/session/{id}/voice/end', [HelperSessionController::class, 'endVoice'])->name('session.voice.end');
    Route::get('/session/{id}/notes', [HelperSessionController::class, 'notes'])->name('session.notes');
    Route::post('/session/{id}/notes', [HelperSessionController::class, 'storeNotes'])->name('session.notes.store');
    Route::post('/session/{id}/end', [HelperSessionController::class, 'end'])->name('session.end');

    // Calendar
    Route::get('/calendar', [HelperCalendarController::class, 'index'])->name('calendar');

    // Competency
    Route::get('/competency', [HelperCompetencyController::class, 'index'])->name('competency');

    // Resources
    Route::get('/resources', [HelperResourceController::class, 'index'])->name('resources');

    // Notifications
    Route::get('/notifications', [HelperNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [HelperNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [HelperNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/unread-count', [HelperNotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    // Profile
    Route::get('/profile', [HelperProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [HelperProfileController::class, 'update'])->name('profile.update');

    // Settings
    Route::get('/settings', [HelperSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [HelperSettingsController::class, 'update'])->name('settings.update');

    // Legacy entry points (redirect to the session-based pages above)
    Route::get('/chat', [HelperChatController::class, 'index'])->name('chat');
    Route::get('/voice', [HelperVoiceController::class, 'index'])->name('voice');
    Route::get('/notes', [HelperNotesController::class, 'index'])->name('notes');

    // Friendly chat aliases — static/parameterised routes before /chat/{id}
    Route::get('/chat/messages/{id}', [HelperChatController::class, 'messages'])->name('chat.messages');
    Route::post('/chat/send', [HelperChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{id}', [HelperChatController::class, 'show'])->name('chat.show');

    // Guard against the old id-less URL (never 404s)
    Route::get('/session/chat', fn () => redirect()->route('helper.chat'))->name('session.chat.index');
});

// =============================================
// CHAT ROUTES (AJAX / Reverb)
// =============================================
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::post('/chat/send', [App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages/{sessionId}', [App\Http\Controllers\ChatController::class, 'getMessages'])->name('chat.messages');
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