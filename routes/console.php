<?php

use App\Models\Helper;
use App\Models\Session;
use App\Services\QueueManagementService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(QueueManagementService::class)->checkQueueAging())
    ->everyTwoMinutes();

Schedule::call(function () {
    Helper::where('availability', 'break')
        ->where('break_started_at', '<=', now()->subMinutes(Helper::BREAK_TIMEOUT_MINUTES))
        ->get()
        ->each(function (Helper $helper) {
            $helper->setAvailability('unavailable', 'Auto-marked after break timeout');
            Log::info('Helper auto-marked unavailable after break timeout', ['helper_id' => $helper->id]);
        });
})->everyFiveMinutes();

Schedule::call(function () {
    Helper::where('current_shift_sessions', '>', 0)->update(['current_shift_sessions' => 0]);
    Log::info('Helper shift sessions reset');
})->dailyAt('23:00');

Schedule::call(function () {
    Helper::where('current_shift_sessions', '>', 0)->update(['current_shift_sessions' => 0]);
    Log::info('Helper shift sessions reset at start of day');
})->dailyAt('06:00');

Schedule::call(function () {
    Session::where('session_status', Session::STATUS_COMPLETED)
        ->where('end_time', '<=', now()->subHours(48))
        ->where('seeker_evaluation_submitted', false)
        ->get()
        ->each(function (Session $session) {
            $session->update([
                'auto_completed' => true,
                'auto_completed_at' => now(),
            ]);

            Log::info('Session auto-completed', ['session_id' => $session->id]);
        });
})->daily();

Schedule::command('sessions:mark-abandoned')->hourly();
Schedule::command('identity-vault:purge-expired')->daily()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\Session::where('session_status', 'active')
        ->where('start_time', '<=', now()->subMinutes(90))
        ->eachById(fn ($session) => app(\App\Services\SessionDurationService::class)->expire($session));
})->everyMinute()->name('expire-chat-sessions')->withoutOverlapping();

Schedule::call(fn () => app(\App\Services\HelperMatchingService::class)->matchWaitingRequests())
    ->everyMinute()->name('match-waiting-seekers')->withoutOverlapping();
