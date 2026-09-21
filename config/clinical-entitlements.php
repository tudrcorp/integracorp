<?php

declare(strict_types=1);

return [
    'otp' => [
        'digits' => 6,
        'ttl_minutes' => 5,
        'resend_wait_seconds' => 120,
        'max_attempts' => 3,
        'reason_min_length' => 10,
    ],
];
