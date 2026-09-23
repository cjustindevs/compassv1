<?php
return [
    // Populate only after institutional review of the versioned questionnaire and rule mapping.
    'approval_reference' => env('SCREENING_APPROVAL_REFERENCE'),
    'approved_version' => env('SCREENING_APPROVED_VERSION'),
];
