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
        $email = mb_strtolower(trim($user->email));
        $reserved = preg_match('/\.(local|test|invalid|example)$/i', $email) === 1;
        StaffMail::firstOrCreate(['event_key' => $eventKey], ['user_id' => $user->id, 'payload' => ['email' => $email, 'subject' => $subject, 'message' => $message], 'available_at' => now(),
            'attempts' => $reserved ? 5 : 0, 'failure_code' => $reserved ? 'non_deliverable_address' : null]);
    }

    public function deliver(): void
    {
        if (! Schema::hasTable('staff_mail_outbox')) {
            return;
        }
        Cache::lock('staff-mail-delivery', 120)->get(function () {
            foreach (StaffMail::whereNull('sent_at')->where('attempts', '<', 5)->where('available_at', '<=', now())
                ->orderByRaw("CASE WHEN event_key LIKE 'emergency-alert:%' THEN 0 ELSE 1 END")->orderBy('id')->limit(3)->get() as $mail) {
                try {
                    $payload = $mail->payload;
                    $html = view('emails.staff-update', ['message' => $payload['message']])->render();
                    if (app(OtpMailConfiguration::class)->usesResendApi()) {
                        $providerId = app(ResendOtpMail::class)->sendHtml($payload['email'], $payload['subject'], $html, 'staff-update', 'staff-mail-'.$mail->id);
                    } else {
                        if (! app(OtpMailConfiguration::class)->canDeliver(config('mail.default'))) {
                            throw new MailDeliveryFailure('non_delivering_mailer');
                        }
                        Mail::html($html, fn ($m) => $m->to($payload['email'])->subject($payload['subject']));
                        $providerId = null;
                    }
                    $mail->update(['sent_at' => now(), 'failure_code' => null, 'attempts' => $mail->attempts + 1, 'provider_message_id' => $providerId]);
                    Log::info('Staff email accepted by provider.', ['outbox_id' => $mail->id, 'provider_message_id' => $providerId]);
                } catch (\Throwable $e) {
                    $reason = $e instanceof MailDeliveryFailure ? $e->reason : 'delivery_failed';
                    $mail->update(['attempts' => $mail->attempts + 1, 'available_at' => now()->addMinutes(5), 'failure_code' => $reason]);
                    Log::warning('Staff email delivery failed.', ['outbox_id' => $mail->id, 'reason' => $reason, 'attempt' => $mail->attempts, 'will_retry' => $mail->attempts < 5]);
                }
            }
        });
    }
}
