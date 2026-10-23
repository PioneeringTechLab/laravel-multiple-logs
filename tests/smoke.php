<?php

// Run against a fresh Laravel 12 application, before and after config:cache.
// This script intentionally does not register the provider itself.
use CSUNMetaLab\MultipleLogs\Facades\AuditLog;
use CSUNMetaLab\MultipleLogs\Facades\AuthLog;
use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;

$appPath = $argv[1] ?? null;
$phase = $argv[2] ?? 'uncached';

if ($appPath === null || ! is_file($appPath.'/bootstrap/app.php')) {
    fwrite(STDERR, "Usage: php tests/smoke.php /absolute/path/to/fresh-laravel-app [uncached|cached]\n");
    exit(1);
}

require $appPath.'/vendor/autoload.php';
$app = require $appPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$check($app->bound('audit_log') && $app->bound('auth_log'), 'Package discovery failed.');
$check(config('multiple-logs.driver') === 'daily', 'Expected the daily test driver.');
$check(config('multiple-logs.level') === 'warning', 'Expected the warning test level.');
$check((int) config('multiple-logs.days') === 2, 'Expected two retained files.');
$check($app->configurationIsCached() === ($phase === 'cached'), 'Unexpected config cache state.');
$check(auditLogger() === app(AuditLogger::class), 'Typed resolution does not match the helper.');

$auditMessage = 'audit-smoke-'.$phase;
$authMessage = 'auth-smoke-'.$phase;
AuditLog::info('filtered-smoke-'.$phase);
AuditLog::warning($auditMessage.' {actor}', ['actor' => 42]);
authLogger()->error($authMessage);
$check(authLogger() === AuthLog::getFacadeRoot(), 'Auth facade and helper do not match.');

$audit = file_get_contents(storage_path('logs/audit-'.date('Y-m-d').'.log'));
$auth = file_get_contents(storage_path('logs/auth-'.date('Y-m-d').'.log'));
$check(str_contains($audit, $auditMessage.' 42'), 'Audit log or placeholder interpolation failed.');
$check(str_contains($auth, $authMessage), 'Authentication log failed.');
$check(! str_contains($audit, $authMessage), 'Authentication message leaked into audit log.');
$check(! str_contains($auth, $auditMessage), 'Audit message leaked into authentication log.');
$check(! str_contains($audit, 'filtered-smoke-'.$phase), 'Minimum level was ignored.');

Log::emergency('native-smoke-'.$phase);
$native = file_get_contents(storage_path('logs/laravel.log'));
$check(str_contains($native, 'native-smoke-'.$phase), 'Native Laravel logging stopped working.');
$check(! str_contains($native, $auditMessage), 'Audit message leaked into native log.');
$check(! str_contains($native, $authMessage), 'Authentication message leaked into native log.');

echo "Laravel 12 smoke test passed ({$phase}).\n";
