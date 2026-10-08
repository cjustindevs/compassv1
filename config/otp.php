<?php

return [
    // Explicit local/testing demonstrations only; production always sends real mail.
    'demo_mode' => env('OTP_DEMO_MODE', false),
    // Legacy Laravel mail delivery is available only for explicit local/testing use.
    'delivery_driver' => env('OTP_DELIVERY_DRIVER', 'resend'),
];
