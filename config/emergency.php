<?php

return [
    // Operational follow-up intervals. Zero disables the corresponding reminder.
    'reminder_minutes' => (int) env('EMERGENCY_REMINDER_MINUTES', 5),
    'progress_reminder_minutes' => (int) env('EMERGENCY_PROGRESS_REMINDER_MINUTES', 15),
];
