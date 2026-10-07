<?php

return [
    // Set only to an institution-approved acknowledgment reminder interval.
    // Zero disables timed reminders; immediate alerts remain enabled.
    'reminder_minutes' => (int) env('EMERGENCY_REMINDER_MINUTES', 0),
];
