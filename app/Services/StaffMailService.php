<?php

namespace App\Services;

use App\Models\StaffMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class StaffMailService
{
    public function enqueue(User $user, string $eventKey, string $subject, string $message): void
    {
        if (! in_array($user->role, ['helper', 'moderator', 'adviser', 'professional'], true)) {
            return;
        }
        StaffMail::firstOrCreate(['event_key' => $eventKey], ['user_id' => $user->id, 'payload' => ['email' => $user->email, 'subject' => $subject, 'message' => $message], 'available_at' => now()]);
    }

    public function deliver(): void
    {
        if (! Schema::hasTable('staff_mail_outbox')) {
            return;
        }
        Cache::lock('staff-mail-delivery', 120)->get(function () {
            foreach (StaffMail::whereNull('sent_at')->where('attempts', '<', 5)->where('available_at', '<=', now())->orderBy('id')->limit(3)->get() as $mail) {
                try {
                    $payload = $mail->payload;
                    $html = view('emails.staff-update', ['message' => $payload['message']])->render();
                    if (app(OtpMailConfiguration::class)->usesResendApi()) {
                        app(ResendOtpMail::class)->sendHtml($payload['email'], $payload['subject'], $html, 'staff-update', 'staff-mail-'.$mail->id);
                    } else {
                        Mail::html($html, fn ($m) => $m->to($payload['email'])->subject($payload['subject']));
                    }
                    $mail->update(['sent_at' => now(), 'failure_code' => null, 'attempts' => $mail->attempts + 1]);
                } catch (\Throwable $e) {
                    $mail->update(['attempts' => $mail->attempts + 1, 'available_at' => now()->addMinutes(5), 'failure_code' => 'delivery_failed']);
                    Log::warning('Staff email delivery will retry.', ['outbox_id' => $mail->id]);
                }
            }
        });
    }
}
