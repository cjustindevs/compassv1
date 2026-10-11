<?php

namespace App\Services;

use Illuminate\Auth\Notifications\ResetPassword;

class ResendPasswordResetChannel
{
    public function send($notifiable, ResetPassword $notification): void
    {
        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        if (! $recipient) {
            return;
        }
        $mail = $notification->toMail($notifiable);
        app(ResendOtpMail::class)->sendHtml(
            $recipient, 'COMPASS - Reset your password', (string) $mail->render(), 'password-reset'
        );
    }
}
