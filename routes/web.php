<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupRestoreController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceLibraryController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Adviser\AdviserCalendarController;
use App\Http\Controllers\Adviser\AdviserDashboardController;
use App\Http\Controllers\Adviser\AdviserEmergencyController;
use App\Http\Controllers\Adviser\AdviserEvaluationController;
use App\Http\Controllers\Adviser\AdviserHelperController;
use App\Http\Controllers\Adviser\AdviserNotificationController;
use App\Http\Controllers\Adviser\AdviserReferralController;
use App\Http\Controllers\Adviser\AdviserReportController;
use App\Http\Controllers\Adviser\AdviserResourceController;
use App\Http\Controllers\Adviser\AdviserSessionController;
use App\Http\Controllers\Adviser\AdviserSettingsController;
use App\Http\Controllers\Adviser\AdviserTrainingController;
use App\Http\Controllers\Adviser\AdviserTranscriptController;
use App\Http\Controllers\Auth\HelpSeekerRegisterController;
use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Helper\HelperCalendarController;
use App\Http\Controllers\Helper\HelperCaseController;
use App\Http\Controllers\Helper\HelperChatController;
use App\Http\Controllers\Helper\HelperCompetencyController;
use App\Http\Controllers\Helper\HelperDashboardController;
use App\Http\Controllers\Helper\HelperNotificationController;
use App\Http\Controllers\Helper\HelperProfileController;
use App\Http\Controllers\Helper\HelperReadinessController;
use App\Http\Controllers\Helper\HelperResourceController;
use App\Http\Controllers\Helper\HelperSelfHelpController;
use App\Http\Controllers\Helper\HelperSessionController;
use App\Http\Controllers\Helper\HelperSettingsController;
use App\Http\Controllers\IdentityVaultController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Moderator\ModeratorAnalyticsController;
use App\Http\Controllers\Moderator\ModeratorDashboardController;
use App\Http\Controllers\Moderator\ModeratorEmergencyController;
use App\Http\Controllers\Moderator\ModeratorManageController;
use App\Http\Controllers\Moderator\ModeratorNotificationController;
use App\Http\Controllers\Moderator\ModeratorQueueController;
use App\Http\Controllers\Moderator\ModeratorReferralController;
use App\Http\Controllers\Moderator\ModeratorReportController;
use App\Http\Controllers\Moderator\ModeratorScheduleController;
use App\Http\Controllers\Moderator\ModeratorSessionController;
use App\Http\Controllers\Moderator\ModeratorSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Professional\ProfessionalCaseController;
use App\Http\Controllers\Professional\ProfessionalDashboardController;
use App\Http\Controllers\Professional\ProfessionalProfileController;
use App\Http\Controllers\Professional\ProfessionalReferralController;
use App\Http\Controllers\Professional\ProfessionalReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralWorkflowController;
use App\Http\Controllers\RequestSupportController;
use App\Http\Controllers\RiskClassificationController;
use App\Http\Controllers\ScreeningReviewController;
use App\Http\Controllers\SeekerConsentController;
use App\Http\Controllers\SeekerDashboardController;
use App\Http\Controllers\SeekerOnboardingController;
use App\Http\Controllers\SelfHelpController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Models\EmergencyAlert;
use App\Models\EmergencyResource;
use App\Models\IncidentReport;
use App\Models\Session;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Role and record authorization are enforced by the vault gateway and logged there.
Route::middleware(['auth', 'throttle:20,1'])->group(function () {
    Route::get('/referrals/{referral}/identity', [IdentityVaultController::class, 'form'])->name('identity.form');
    Route::post('/referrals/{referral}/identity', [IdentityVaultController::class, 'store'])->name('identity.store');
    Route::post('/referrals/{referral}/identity/release', [IdentityVaultController::class, 'release'])->name('identity.release');
    Route::get('/referrals/{referral}/identity/released', [IdentityVaultController::class, 'show'])->name('identity.show');
    Route::post('/referrals/{referral}/identity/acknowledge', [IdentityVaultController::class, 'acknowledge'])->name('identity.acknowledge');
    Route::post('/sessions/{session}/identity/emergency', [IdentityVaultController::class, 'emergency'])->name('identity.emergency');
    Route::post('/sessions/{session}/identity/emergency-review', [IdentityVaultController::class, 'reviewEmergency'])->name('identity.emergency-review');
});


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Register the broadcasting auth endpoints (/broadcasting/auth) so Echo can
// authorize private channels (session.*, helper.*, etc.) for authenticated users.
Broadcast::routes();

// =============================================
// PWA â€” serve manifest + service worker with correct MIME
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
    Route::get('/seeker/dashboard', [SeekerDashboardController::class, 'index'])->middleware('role:seeker')->name('seeker.dashboard');
    Route::get('/helper/dashboard', [HelperDashboardController::class, 'index'])->name('helper.dashboard');
    Route::get('/adviser/dashboard', [AdviserDashboardController::class, 'index'])->name('adviser.dashboard');

    Route::get('/admin/dashboard', DashboardController::class)->middleware('role:admin')->name('admin.dashboard');
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
Route::get('/register', [SeekerOnboardingController::class, 'create'])->name('register');
Route::get('/register-seeker', [SeekerOnboardingController::class, 'create'])->name('seeker.register');

// =============================================
// REQUEST SUPPORT ROUTES (3-Step Process)
// =============================================
Route::middleware(['auth', 'role:seeker'])->group(function () {
    // Step 1: Screening (Area of Concern, Description, Safety Check)
    Route::get('/request/screening', [RequestSupportController::class, 'screening'])->name('request.screening');
    Route::post('/request/screening', [RequestSupportController::class, 'processScreening'])->name('request.screening.process');

    Route::get('/request/concern', [RequestSupportController::class, 'concern'])->name('request.concern');
    Route::post('/request/concern', [RequestSupportController::class, 'processConcern'])->name('request.concern.process');
    Route::post('/request/{session}/cancel', [RequestSupportController::class, 'cancel'])->name('request.cancel');
    Route::get('/seeker/requests', [RequestSupportController::class, 'history'])->name('seeker.requests');
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
    Route::post('/session/check-in', [SessionController::class, 'checkIn'])->name('session.checkin');
    Route::get('/session/voice', [SessionController::class, 'voice'])->name('session.voice');
    Route::post('/session/end', [SessionController::class, 'endSession'])->name('session.end');
    Route::get('/session/evaluation', [SessionController::class, 'evaluation'])->name('session.evaluation');
    Route::post('/session/evaluation', [SessionController::class, 'processEvaluation'])->name('session.evaluation.process');
    Route::get('/session/thank-you', [SessionController::class, 'thankYou'])->name('session.thank-you');
    Route::get('/session/history', [SessionController::class, 'history'])->name('session.history');

    // Additional Seeker Pages

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
});

// =============================================
// HELPER MODULE ROUTES
// =============================================
// Helper routes that can only be accessed once the helper has a valid,
// current readiness check. Anything gated in here is off-limits to helpers
// who are not ready.
Route::middleware(['auth', 'role:helper', 'ensure.helper.profile', 'ensure.helper.readiness'])->prefix('helper')->name('helper.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [HelperDashboardController::class, 'index'])->name('dashboard');

    // Assigned Cases
    Route::get('/cases', [HelperCaseController::class, 'index'])->name('cases');
    Route::get('/sessions', [HelperCaseController::class, 'index'])->name('sessions');
    Route::get('/cases/{id}', [HelperCaseController::class, 'show'])->name('cases.show');
    Route::get('/session/{id}', [HelperCaseController::class, 'show'])->whereNumber('id')->name('session.view');
    Route::post('/cases/{id}/accept', [HelperCaseController::class, 'accept'])->name('cases.accept');
    Route::post('/cases/{id}/decline', [HelperCaseController::class, 'decline'])->name('cases.decline');

    Route::get('/session/{id}/pre-assessment', [HelperSessionController::class, 'preSessionAssessment'])->name('session.pre-assessment');
    Route::post('/session/{id}/start', [HelperSessionController::class, 'startSessionFromPreAssessment'])->name('session.start');

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
    Route::get('/documentation/{id}', [HelperSessionController::class, 'notes'])->name('documentation');
    Route::post('/documentation/{id}', [HelperSessionController::class, 'storeNotes'])->name('documentation.submit');
    Route::get('/reflection/{id}', [HelperSessionController::class, 'notes'])->name('reflection');
    Route::post('/reflection/{id}', [HelperSessionController::class, 'storeReflection'])->name('reflection.submit');
    Route::post('/session/{id}/emergency', [HelperSessionController::class, 'flagEmergency'])->name('session.emergency');
    Route::post('/session/{id}/referral/consent', [HelperSessionController::class, 'requestReferralConsent'])->name('session.referral.consent');
    Route::post('/session/{id}/referral', [HelperSessionController::class, 'recommendReferral'])->name('session.referral');
    Route::get('/referral/status/{id}', [HelperSessionController::class, 'referralStatus'])->name('referral.status');
    Route::post('/referral/{id}/clarification', [HelperSessionController::class, 'clarifyReferral'])->name('referral.clarify');

    // Calendar
    Route::get('/calendar', [HelperCalendarController::class, 'index'])->name('calendar');

    // Competency
    Route::post('/feedback/{id}/acknowledge', [HelperCompetencyController::class, 'acknowledgeFeedback'])->name('feedback.acknowledge');
    Route::get('/competency', [HelperCompetencyController::class, 'index'])->name('competency');
    Route::get('/competency/{id}', [HelperCompetencyController::class, 'show'])->name('competency.view');
    Route::get('/feedback', [HelperCompetencyController::class, 'feedback'])->name('feedback');
    Route::get('/feedback/{id}', [HelperCompetencyController::class, 'feedbackShow'])->name('feedback.view');

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

    // Chat index (shows active chats / redirects to active session)
    Route::get('/chat', [HelperChatController::class, 'index'])->name('chat');
    Route::get('/chat/{id}', [HelperChatController::class, 'show'])->name('chat.show');
});

// Helper routes that must be reachable regardless of readiness: the readiness
// assessment, availability, onboarding, and self-help tools. These come first
// so a helper who just logged in (and isn't ready) can always reach them.
Route::middleware(['auth', 'role:helper', 'ensure.helper.profile'])->prefix('helper')->name('helper.')->group(function () {
    // Availability (documentation-compatible aliases backed by readiness/status)
    Route::get('/availability', [HelperReadinessController::class, 'index'])->name('availability');
    Route::post('/availability/update', [HelperReadinessController::class, 'updateAvailability'])->name('availability.update');

    // Readiness Check
    Route::get('/readiness', [HelperReadinessController::class, 'index'])->name('readiness');
    Route::get('/readiness/status', [HelperReadinessController::class, 'status'])->name('readiness.status');
    Route::post('/readiness', [HelperReadinessController::class, 'store'])->name('readiness.store');
    Route::get('/readiness/history', [HelperReadinessController::class, 'history'])->name('readiness.history');

    // Self-help tools (usable while not ready)
    Route::get('/self-help', [HelperSelfHelpController::class, 'index'])->name('self-help');
    Route::get('/self-help/breathing', [HelperSelfHelpController::class, 'breathing'])->name('self-help.breathing');
    Route::get('/self-help/grounding', [HelperSelfHelpController::class, 'grounding'])->name('self-help.grounding');
    Route::get('/self-help/journal', [HelperSelfHelpController::class, 'journal'])->name('self-help.journal');
    Route::post('/self-help/journal', [HelperSelfHelpController::class, 'storeJournal'])->name('self-help.journal.store');
    Route::get('/self-help/hotlines', [HelperSelfHelpController::class, 'hotlines'])->name('self-help.hotlines');

    // Onboarding â€” first-time helpers with no helper record land here.
    Route::get('/onboarding', [HelperProfileController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [HelperProfileController::class, 'storeOnboarding'])->name('onboarding.store');
});
// =============================================
// ADVISER MODULE ROUTES
// =============================================
Route::middleware(['auth', 'role:adviser'])->prefix('adviser')->name('adviser.')->group(function () {
    Route::get('/training', [AdviserTrainingController::class, 'index'])->name('training');
    Route::post('/training', [AdviserTrainingController::class, 'store'])->name('training.store');
    Route::patch('/training/{training}', [AdviserTrainingController::class, 'update'])->name('training.update');
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
    Route::post('/helpers/reassign', [AdviserHelperController::class, 'reassignHelpers'])->name('helpers.reassign');
    Route::get('/helpers/availability', [AdviserHelperController::class, 'availability'])->name('helpers.availability');
    Route::get('/helper/{id}', [AdviserHelperController::class, 'show'])->name('helper.show');
    Route::get('/helper/{id}/matching', [AdviserHelperController::class, 'matching'])->name('helper.matching');
    Route::post('/helper/{id}/verify', [AdviserHelperController::class, 'verify'])->name('helper.verify');
    Route::post('/helper/{id}/competency', [AdviserHelperController::class, 'updateCompetency'])->name('helper.competency.update');
    Route::post('/helper/{id}/assign', [AdviserHelperController::class, 'assign'])->name('helper.assign');

    // Schedule Management
    Route::get('/schedule', [AdviserHelperController::class, 'manageSchedule'])->name('schedule');
    Route::post('/schedule/update', [AdviserHelperController::class, 'updateSchedule'])->name('schedule.update');
    Route::post('/schedule/destroy', [AdviserHelperController::class, 'destroySchedule'])->name('schedule.destroy');

    // Transcript Review
    Route::get('/transcripts', [AdviserTranscriptController::class, 'index'])->name('transcripts');
    Route::post('/transcript/{sessionId}/verify', [AdviserTranscriptController::class, 'verify'])->name('transcript.verify');
    Route::post('/transcripts/{sessionId}/access', [AdviserTranscriptController::class, 'access'])->name('transcript.access');

    // Reports
    Route::get('/reports', [AdviserReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [AdviserReportController::class, 'export'])->name('reports.export');

    // Emergency Management
    Route::get('/emergencies', [AdviserEmergencyController::class, 'index'])->name('emergencies');
    Route::get('/emergencies/{id}', [AdviserEmergencyController::class, 'show'])->name('emergencies.show');
    Route::post('/emergencies/{id}/resolve', [AdviserEmergencyController::class, 'resolve'])->name('emergencies.resolve');
    Route::post('/emergencies/{id}/action', [AdviserEmergencyController::class, 'action'])->name('emergencies.action');

    // Calendar
    Route::get('/calendar', [AdviserCalendarController::class, 'index'])->name('calendar');
    Route::post('/calendar/event', [AdviserCalendarController::class, 'store'])->name('calendar.event.store');

    // Resources
    Route::get('/resources', [AdviserResourceController::class, 'index'])->name('resources');
    Route::post('/emergency-resources', [AdviserResourceController::class, 'saveEmergency'])->name('emergency-resources.save');
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
    Route::post('/referral/{referral}/appointment', [ProfessionalReferralController::class, 'schedule'])->name('referral.appointment');
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

// Referrals awaiting an Adviser assignment. Administrators are notified of
// this queue alongside Moderators, so they must be able to open and action it.
Route::middleware(['auth', 'role:moderator,admin'])->prefix('moderator')->name('moderator.')->group(function () {
    Route::get('/referrals/unassigned', [ModeratorReferralController::class, 'index'])->name('referrals.unassigned');
    Route::post('/referrals/{referral}/assign-adviser', [ModeratorReferralController::class, 'assign'])->name('referrals.assign-adviser');
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
    Route::patch('/queue/{queue}/priority', [ModeratorQueueController::class, 'priority'])->name('queue.priority');
    Route::post('/queue/reassign', [ModeratorQueueController::class, 'reassign'])->name('queue.reassign');
    Route::post('/queue/schedule', [ModeratorQueueController::class, 'schedule'])->name('queue.schedule');
    Route::delete('/queue/{id}/remove', [ModeratorQueueController::class, 'removeFromQueue'])->name('queue.remove');
    Route::get('/queue/stats', [ModeratorQueueController::class, 'stats'])->name('queue.stats');

    // Active Sessions
    Route::get('/sessions', [ModeratorSessionController::class, 'index'])->name('sessions');
    Route::get('/sessions/stats', [ModeratorSessionController::class, 'stats'])->name('sessions.stats');
    Route::get('/sessions/{id}', [ModeratorSessionController::class, 'show'])->name('sessions.show');

    // Schedule Management
    Route::get('/schedules', [ModeratorScheduleController::class, 'index'])->name('schedules');
    Route::post('/schedules', [ModeratorScheduleController::class, 'store'])->name('schedules.store');
    Route::post('/schedules/destroy', [ModeratorScheduleController::class, 'destroy'])->name('schedules.destroy');

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
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');
    Route::get('/chat/messages/{sessionId}', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::post('/chat/expire/{sessionId}', [ChatController::class, 'expire'])->whereNumber('sessionId')->name('chat.expire');
    Route::get('/chat/status/{sessionId}', [ChatController::class, 'status'])->whereNumber('sessionId')->name('chat.status');
    Route::get('/chat/transcript/{sessionId}', [ChatController::class, 'getTranscript'])->name('chat.transcript');
    Route::get('/transcript/{sessionId}/download', [ChatController::class, 'downloadTranscript'])->name('api.transcript.download');

    Route::post('/risk/classify', [RiskClassificationController::class, 'classify'])->name('risk.classify');
    Route::patch('/risk/classification', [RiskClassificationController::class, 'updateRiskClassification'])->name('risk.update');

    Route::post('/referrals', [ReferralWorkflowController::class, 'store'])->name('referrals.store');
    Route::post('/referrals/{referral}/review', [ReferralWorkflowController::class, 'review'])->name('referrals.review');
    Route::post('/referrals/{referral}/consent', [ReferralWorkflowController::class, 'consent'])->name('referrals.consent');
    Route::post('/referrals/{referral}/consent-request', [ReferralWorkflowController::class, 'consentRequest'])->name('referrals.consent-request');
    Route::post('/referrals/{referral}/accept', [ReferralWorkflowController::class, 'accept'])->name('referrals.accept');
    Route::patch('/referrals/{referral}/outcome', [ReferralWorkflowController::class, 'outcome'])->name('referrals.outcome');

    Route::post('/incidents', [IncidentReportController::class, 'store'])->name('incidents.store');
    Route::post('/incidents/{incident}/review', [IncidentReportController::class, 'review'])->name('incidents.review');
    Route::post('/incidents/{incident}/escalate', [IncidentReportController::class, 'escalate'])->name('incidents.escalate');
    Route::post('/incidents/{incident}/resolve', [IncidentReportController::class, 'resolve'])->name('incidents.resolve');
    Route::post('/incidents/{incident}/close', [IncidentReportController::class, 'close'])->name('incidents.close');
});

// =============================================
// API ROUTES (for AJAX calls)
// =============================================
Route::prefix('api')->group(function () {
    Route::post('/send-otp', [OTPController::class, 'sendOTP'])->middleware(['guest', 'throttle:registration-actions'])->name('registration.otp.send');
    Route::post('/resend-otp', [OTPController::class, 'sendOTP'])->middleware(['guest', 'throttle:registration-actions']);
    Route::post('/verify-otp', [OTPController::class, 'verifyOTP'])->middleware(['guest', 'throttle:registration-actions'])->name('registration.otp.verify');
    Route::post('/shuffle-alias', [SeekerOnboardingController::class, 'shuffleAlias'])->middleware(['guest', 'throttle:registration-actions'])->name('registration.alias.shuffle');
    // Retired legacy account creation routes
    foreach (['check-email', 'register-seeker'] as $legacyEndpoint) {
        Route::post('/'.$legacyEndpoint, fn () => response()->json(['message' => 'Email-based seeker registration has been retired. Use pseudonymous onboarding.', 'registration_url' => route('seeker.register')], 410));
    }

    // Help Seeker Registration
    Route::get('/generate-alias', [HelpSeekerRegisterController::class, 'generateAlias']);
});

Route::post('/onboarding', [SeekerOnboardingController::class, 'store'])->middleware(['guest', 'throttle:seeker-registration'])->name('seeker.onboarding.store');
Route::middleware(['auth', 'role:seeker'])->group(function () {
    Route::get('/seeker/consent', [SeekerOnboardingController::class, 'consent'])->name('seeker.consent');
    Route::post('/seeker/consent', [SeekerOnboardingController::class, 'accept'])->name('seeker.consent.accept');
});

Route::get('/session/{session}/referral-prompt', function (Session $session) {
    $user = auth()->user();
    abort_unless(($user->helpSeeker && $user->helpSeeker->id === $session->seeker_id) || ($user->helper && $user->helper->id === $session->helper_id), 403);
    $referral = $session->referrals()->latest('id')->first();
    $consent = $referral ? $referral->consentRecords()->where('purpose', 'referral')->latest('id')->first() : null;
    $emergency = EmergencyAlert::where('session_id', $session->id)->latest('id')->first();
    $incident = IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->latest('id')->first();

    return response()->json([
        'referral' => $referral ? [
            'id' => $referral->id, 'status' => $referral->status,
            'priority_level' => $referral->priority_level,
            'help_seeker_consent' => (bool) $referral->help_seeker_consent,
            'summary' => $referral->referral_reason,
            'consent_url' => route('referrals.consent-request', $referral),
            'identity_url' => route('identity.form', $referral),
        ] : null,
        'consent' => $consent ? ['decision' => $consent->decision, 'consent_given' => (bool) $consent->consent_given, 'withdrawn' => (bool) $consent->withdrawn, 'scope' => $consent->scope] : null,
        'emergency' => $incident ? ['alert_id' => $incident->id, 'status' => $incident->status, 'risk_level' => 'emergency'] : null,
    ]);
})->middleware('auth')->name('session.referral-prompt');

Route::get('/emergency', fn () => view('emergency', ['hotlines' => EmergencyResource::published()->get()]))->middleware('auth')->name('emergency');
Route::middleware(['auth', 'role:seeker'])->group(function () {
    Route::get('/seeker/privacy', [SeekerConsentController::class, 'index'])->name('seeker.privacy');
    Route::post('/seeker/privacy/decision', [SeekerConsentController::class, 'decision'])->name('seeker.privacy.decision');
    Route::get('/seeker/referrals', [SeekerConsentController::class, 'referrals'])->name('seeker.referrals');
});
Route::middleware(['auth', 'role:adviser'])->group(function () {
    Route::get('/adviser/screenings', [ScreeningReviewController::class, 'index'])->name('adviser.screenings');
    Route::get('/adviser/screenings/{session}/conversation', [ScreeningReviewController::class, 'conversation'])->name('adviser.screenings.conversation');
    Route::post('/adviser/screenings/{session}', [ScreeningReviewController::class, 'review'])->name('adviser.screenings.review');
});

Route::middleware(['auth', 'role:helper'])->group(function () {
    Route::get('/helper/training', [AdviserTrainingController::class, 'index'])->name('helper.training');
    Route::patch('/helper/training/{training}', [AdviserTrainingController::class, 'update'])->name('helper.training.update');
});

// Integrated Admin module; the existing active-account and role middleware remain authoritative.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/roles-permissions', RolePermissionController::class)->name('roles-permissions');
    Route::get('/resource-library', ResourceLibraryController::class)->name('resource-library');
    Route::get('/audit-logs', AuditLogController::class)->name('audit-logs');
    Route::get('/backup-restore', BackupRestoreController::class)->name('backup-restore');
    Route::get('/system-health', SystemHealthController::class)->name('system-health');
    Route::get('/reports', ReportController::class)->name('reports');
    Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings/preferences', [App\Http\Controllers\Admin\SettingsController::class, 'updatePreference'])->name('settings.preference.update');
});
