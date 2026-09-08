<?php

return [
    'key' => env('IDENTITY_VAULT_ENCRYPTION_KEY'),
    'retention_days' => 365,
    // Designation is deployment-controlled, never taken from a request body.
    'emergency_responder_ids' => array_filter(array_map('intval', explode(',', env('IDENTITY_VAULT_RESPONDER_IDS', '')))),
];
