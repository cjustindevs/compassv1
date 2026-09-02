<?php

use App\Http\Controllers\Adviser\AdviserCalendarController;
use App\Http\Controllers\Adviser\AdviserDashboardController;
use App\Http\Controllers\Adviser\AdviserEvaluationController;
use App\Http\Controllers\Adviser\AdviserHelperController;
use App\Http\Controllers\Adviser\AdviserNotificationController;
use App\Http\Controllers\Adviser\AdviserReferralController;
use App\Http\Controllers\Adviser\AdviserReportController;
use App\Http\Controllers\Adviser\AdviserResourceController;
use App\Http\Controllers\Adviser\AdviserSessionController;
use App\Http\Controllers\Adviser\AdviserSettingsController;
use App\Http\Controllers\Auth\HelpSeekerRegisterController;
use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\Helper\HelperCalendarController;
use App\Http\Controllers\Helper\HelperCaseController;
use App\Http\Controllers\Helper\HelperChatController;
use App\Http\Controllers\Helper\HelperDashboardController;
use App\Http\Controllers\Helper\HelperCompetencyController;
use App\Http\Controllers\Helper\HelperNotificationController;
use App\Http\Controllers\Helper\HelperProfileController;
use App\Http\Controllers\Helper\HelperReadinessController;
use App\Http\Controllers\Helper\HelperResourceController;
use App\Http\Controllers\Helper\HelperSessionController;
use App\Http\Controllers\Helper\HelperSettingsController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Moderator\ModeratorAnalyticsController;
use App\Http\Controllers\Moderator\ModeratorDashboardController;
use App\Http\Controllers\Moderator\ModeratorEmergencyController;
use App\Http\Controllers\Moderator\ModeratorManageController;
use App\Http\Controllers\Moderator\ModeratorNotificationController;
use App\Http\Controllers\Moderator\ModeratorQueueController;
use App\Http\Controllers\Moderator\ModeratorReportController;
use App\Http\Controllers\Moderator\ModeratorSessionController;
use App\Http\Controllers\Moderator\ModeratorSettingsController;
use App\Http\Controllers\Professional\ProfessionalCaseController;
use App\Http\Controllers\Professional\ProfessionalDashboardController;
use App\Http\Controllers\Professional\ProfessionalProfileController;
use App\Http\Controllers\Professional\ProfessionalReferralController;
use App\Http\Controllers\Professional\ProfessionalReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestSupportController;
use App\Http\Controllers\SeekerDashboardController;
use App\Http\Controllers\SelfHelpController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Register the broadcasting auth endpoints (/broadcasting/auth) so Echo can
// authorize private channels (session.*, helper.*, etc.) for authenticated users.
Broadcast::routes();

// =============================================
// PWA — serve manifest + service worker with correct MIME
// (keeps install / install-prompt / icons working regardless of the
//  static-file handling of the hosting server).
// =============================================
Route::get('/manifest.json', function () {
    return response()->file(public_path('manifest.json'))
        ->header('Content-Type', 'application/json')
        ->header('Cache-Control', 'public, max-age=86400');
})->name('manifest');

Route::get('/sw.js', function () {
    return response()->file(public_path('sw.js'))
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-cache');
})->name('sw');

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
    Route::get('/seeker/dashboard', [SeekerDashboardController::class, 'index'])->name('seeker.dashboard');
    Route::get('/helper/dashboard', [HelperDashboardController::class, 'index'])->name('helper.dashboard');
    Route::get('/adviser/dashboard', [AdviserDashboardController::class, 'index'])->name('adviser.dashboard');

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
    // Step 1: Screening (Area of Concern, Description, Safety Check)
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
    Route::get('/session/thank-you', [SessionController::class, 'thankYou'])->name('session.thank-you');
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
    Route::post('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.store');
    Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme');
});

// =============================================
// HELPER MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:helper', 'ensure.helper.profile', 'ensure.helper.readiness'])->prefix('helper')->name('helper.')->group(function () {
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
    Route::get('/session/chat', function () {
        return redirect()->route('helper.chat');
    })->name('session.chat.legacy');

    Route::get('/session/{id}/chat', [HelperSessionController::class, 'chat'])->name('session.chat');
    Route::post('/session/{id}/chat/send', [HelperSessionController::class, 'sendMessage'])->name('session.chat.send');
    Route::get('/session/{id}/voice', [HelperSessionController::class, 'voice'])->name('session.voice');
    Route::post('/session/{id}/voice/start', [HelperSessionController::class, 'startVoice'])->name('session.voice.start');
    Route::post('/session/{id}/voice/end', [HelperSessionController::class, 'endVoice'])->name('session.voice.end');
    Route::post('/session/{id}/end', [HelperSessionController::class, 'end'])->name('session.end');
    Route::get('/session/{id}/notes', [HelperSessionController::class, 'notes'])->name('session.notes');
    Route::post('/session/{id}/notes', [HelperSessionController::class, 'storeNotes'])->name('session.notes.store');
    Route::post('/session/{id}/emergency', [HelperSessionController::class, 'flagEmergency'])->name('session.emergency');
    Route::post('/session/{id}/referral', [HelperSessionController::class, 'recommendReferral'])->name('session.referral');

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

    // Onboarding — first-time helpers with no helper record land here.
    Route::get('/onboarding', [HelperProfileController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [HelperProfileController::class, 'storeOnboarding'])->name('onboarding.store');

    // Settings
    Route::get('/settings', [HelperSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [HelperSettingsController::class, 'update'])->name('settings.update');

    // Chat index (shows active chats / redirects to active session)
    Route::get('/chat', [HelperChatController::class, 'index'])->name('chat');
    Route::get('/chat/{id}', [HelperChatController::class, 'show'])->name('chat.show');
});

// =============================================
// ADVISER MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:adviser'])->prefix('adviser')->name('adviser.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdviserDashboardController::class, 'index'])->name('dashboard');

    // Live session monitoring (read-only supervision)
    Route::get('/session/{id}', [AdviserSessionController::class, 'show'])->name('session.show');

    // Pending Evaluations
    Route::get('/evaluations', [AdviserEvaluationController::class, 'index'])->name('evaluations');
    Route::get('/evaluate/{id}', [AdviserEvaluationController::class, 'show'])->name('evaluate');
    Route::post('/evaluate/{id}', [AdviserEvaluationController::class, 'store'])->name('evaluate.store');
    Route::post('/evaluations/{id}/skip', [AdviserEvaluationController::class, 'skip'])->name('evaluations.skip');

    // Referral Queue
    Route::get('/referrals', [AdviserReferralController::class, 'index'])->name('referrals');
    Route::get('/referral/{id}', [AdviserReferralController::class, 'show'])->name('referral.show');
    Route::post('/referral/{id}/approve', [AdviserReferralController::class, 'approve'])->name('referral.approve');
    Route::post('/referral/{id}/reject', [AdviserReferralController::class, 'reject'])->name('referral.reject');
    Route::post('/referral/{id}/request-info', [AdviserReferralController::class, 'requestInfo'])->name('referral.request-info');
    Route::post('/referral/{id}/assign', [AdviserReferralController::class, 'assignProfessional'])->name('referral.assign');

    // Helper Management
    Route::get('/helpers', [AdviserHelperController::class, 'index'])->name('helpers');
    Route::get('/helpers/export', [AdviserHelperController::class, 'export'])->name('helpers.export');
    Route::get('/helper/{id}', [AdviserHelperController::class, 'show'])->name('helper.show');
    Route::post('/helper/{id}/assign', [AdviserHelperController::class, 'assign'])->name('helper.assign');

    // Reports
    Route::get('/reports', [AdviserReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [AdviserReportController::class, 'export'])->name('reports.export');

    // Calendar
    Route::get('/calendar', [AdviserCalendarController::class, 'index'])->name('calendar');
    Route::post('/calendar/event', [AdviserCalendarController::class, 'store'])->name('calendar.event.store');

    // Resources
    Route::get('/resources', [AdviserResourceController::class, 'index'])->name('resources');
    Route::post('/resources', [AdviserResourceController::class, 'store'])->name('resources.store');
    Route::put('/resources/{id}', [AdviserResourceController::class, 'update'])->name('resources.update');
    Route::delete('/resources/{id}', [AdviserResourceController::class, 'destroy'])->name('resources.destroy');

    // Notifications
    Route::get('/notifications', [AdviserNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [AdviserNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [AdviserNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [AdviserNotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/notifications/unread-count', [AdviserNotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    // Settings
    Route::get('/settings', [AdviserSettingsController::class, 'index'])->name('settings');
    Route::put('/settings/profile', [AdviserSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [AdviserSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/appearance', [AdviserSettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::put('/settings/notifications', [AdviserSettingsController::class, 'updateNotifications'])->name('settings.notifications');
});

// =============================================
// PSYCHOLOGY PROFESSIONAL MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:professional'])->prefix('professional')->name('professional.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [ProfessionalDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [ProfessionalDashboardController::class, 'getStats'])->name('dashboard.stats');

    // Referrals
    Route::get('/referrals', [ProfessionalReferralController::class, 'index'])->name('referrals');
    Route::get('/referral/{id}', [ProfessionalReferralController::class, 'show'])->name('referral.show');
    Route::post('/referral/{id}/accept', [ProfessionalReferralController::class, 'accept'])->name('referral.accept');
    Route::post('/referral/{id}/decline', [ProfessionalReferralController::class, 'decline'])->name('referral.decline');
    Route::post('/referral/{id}/start', [ProfessionalReferralController::class, 'startCase'])->name('referral.start');

    // Cases
    Route::get('/cases', [ProfessionalCaseController::class, 'index'])->name('cases');
    Route::get('/case/{id}', [ProfessionalCaseController::class, 'show'])->name('cases.show');
    Route::post('/case/{id}/status', [ProfessionalCaseController::class, 'updateStatus'])->name('cases.status');
    Route::post('/case/{id}/notes', [ProfessionalCaseController::class, 'addNotes'])->name('cases.notes');

    // Reports
    Route::get('/reports', [ProfessionalReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [ProfessionalReportController::class, 'export'])->name('reports.export');

    // Profile
    Route::get('/profile', [ProfessionalProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfessionalProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/availability', [ProfessionalProfileController::class, 'updateAvailability'])->name('profile.availability');

    // Settings
    Route::get('/settings', function () {
        return view('professional.settings');
    })->name('settings');
});

// =============================================
// MODERATOR MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:moderator'])->prefix('moderator')->name('moderator.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [ModeratorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [ModeratorDashboardController::class, 'stats'])->name('dashboard.stats');

    // Incoming Queue
    Route::get('/queue', [ModeratorQueueController::class, 'index'])->name('queue');
    Route::post('/queue/assign', [ModeratorQueueController::class, 'assign'])->name('queue.assign');
    Route::post('/queue/reassign', [ModeratorQueueController::class, 'reassign'])->name('queue.reassign');
    Route::delete('/queue/{id}/remove', [ModeratorQueueController::class, 'removeFromQueue'])->name('queue.remove');
    Route::get('/queue/stats', [ModeratorQueueController::class, 'stats'])->name('queue.stats');

    // Active Sessions
    Route::get('/sessions', [ModeratorSessionController::class, 'index'])->name('sessions');
    Route::get('/sessions/stats', [ModeratorSessionController::class, 'stats'])->name('sessions.stats');
    Route::get('/sessions/{id}', [ModeratorSessionController::class, 'show'])->name('sessions.show');

    // Manage (Helpers & Advisers)
    Route::get('/manage', [ModeratorManageController::class, 'index'])->name('manage');
    Route::post('/manage/assign', [ModeratorManageController::class, 'assignToAdviser'])->name('manage.assign');
    Route::post('/manage/unassign', [ModeratorManageController::class, 'unassignFromAdviser'])->name('manage.unassign');
    Route::get('/manage/stats', [ModeratorManageController::class, 'stats'])->name('manage.stats');

    // Emergency Alerts
    Route::get('/emergency', [ModeratorEmergencyController::class, 'index'])->name('emergency');
    Route::post('/emergency/{id}/escalate', [ModeratorEmergencyController::class, 'escalate'])->name('emergency.escalate');
    Route::post('/emergency/{id}/resolve', [ModeratorEmergencyController::class, 'resolve'])->name('emergency.resolve');
    Route::get('/emergency/stats', [ModeratorEmergencyController::class, 'stats'])->name('emergency.stats');

    // Analytics
    Route::get('/analytics', [ModeratorAnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/data', [ModeratorAnalyticsController::class, 'data'])->name('analytics.data');
    Route::get('/analytics/export', [ModeratorAnalyticsController::class, 'export'])->name('analytics.export');

    // Reports
    Route::get('/reports', [ModeratorReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [ModeratorReportController::class, 'export'])->name('reports.export');

    // Notifications
    Route::get('/notifications', [ModeratorNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [ModeratorNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ModeratorNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [ModeratorNotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/notifications/unread-count', [ModeratorNotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    // Settings
    Route::get('/settings', [ModeratorSettingsController::class, 'index'])->name('settings');
    Route::put('/settings/profile', [ModeratorSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [ModeratorSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/appearance', [ModeratorSettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::put('/settings/notifications', [ModeratorSettingsController::class, 'updateNotifications'])->name('settings.notifications');
});

// =============================================
// CHAT ROUTES (AJAX / Reverb)
// =============================================
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages/{sessionId}', [ChatController::class, 'getMessages'])->name('chat.messages');
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