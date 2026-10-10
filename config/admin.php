<?php

return [
    // This first administration space is intentionally restricted to this workstation.
    'username' => env('HOMYA_ADMIN_USERNAME', ''),
    'password_hash' => env('HOMYA_ADMIN_PASSWORD_HASH', ''),
    'idle_seconds' => 1800,
    'absolute_seconds' => 28800,
];
