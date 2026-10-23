<?php

return [
    // Supported drivers: single, daily, syslog, errorlog.
    'driver' => env('MULTIPLE_LOGS_DRIVER', 'single'),

    'level' => env('MULTIPLE_LOGS_LEVEL', env('LOG_LEVEL', 'debug')),

    // Daily file retention. Zero keeps all files. LOG_MAX_DAYS is a legacy fallback.
    'days' => env('MULTIPLE_LOGS_DAYS', env('LOG_MAX_DAYS', 7)),

    'audit_path' => env('MULTIPLE_LOGS_AUDIT_PATH', storage_path('logs/audit.log')),
    'auth_path' => env('MULTIPLE_LOGS_AUTH_PATH', storage_path('logs/auth.log')),

    // These options apply to the single and daily file drivers.
    'permission' => 0644,
    'locking' => false,
];
