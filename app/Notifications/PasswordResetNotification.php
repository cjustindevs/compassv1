<?php

namespace App\Notifications;

use App\Services\OtpMailConfiguration;
use App\Services\ResendPasswordResetChannel;
use Illuminate\Auth\Notifications\ResetPassword;

class PasswordResetNotification extends ResetPassword
{
    public function via($notifiable)
    {
        return app(OtpMailConfiguration::class)->usesResendApi()
            ? [ResendPasswordResetChannel::class] : parent::via($notifiable);
    }
}
