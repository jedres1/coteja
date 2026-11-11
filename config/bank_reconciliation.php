<?php

return [
    // RN-08: reviewer must differ from the person who prepared the reconciliation
    'require_different_reviewer' => env('BANK_RECON_REQUIRE_DIFFERENT_REVIEWER', true),

    // Auto-matching: tolerance in days for date proximity matching
    'match_days_tolerance' => 3,

    // Alert threshold in days for old transit items
    'transit_alert_days' => 60,

    // Chunk size for opening reconciliation bulk mark
    'opening_chunk_size' => 2000,
];
