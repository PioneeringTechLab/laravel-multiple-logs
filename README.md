# Laravel Multiple Logs

Dedicated audit and authentication loggers for Laravel, with convenient facades,
helpers, and an extensible custom logger class.

Laravel already supports multiple log channels. This package provides the
`AuditLog` / `AuthLog` facades and `auditLogger()` / `authLogger()` helpers on top
of Laravel's logging drivers. It does not automatically record authentication
events or model changes; call the loggers from your application or event listeners.

## Compatibility

| Package line | Laravel | PHP | Monolog |
| --- | --- | --- | --- |
| 2.x migration candidate (unreleased) | 12.x | 8.2+ (PHP 8.x) | 3.x |
| 1.x (legacy) | Original Laravel 5 implementation | See the 1.x release | Legacy dependencies |

This checkout contains the proposed 2.x upgrade. The test workflow targets PHP
8.2, 8.3, and 8.4 with Laravel 12. Run the tests before publishing or deploying it;
this README does not claim that the migration has already passed CI.

The Composer package name remains `csun-metalab/laravel-multiple-logs`, and the PHP
namespace remains `CSUNMetaLab\MultipleLogs`. Moving the GitHub repository to
PioneeringTechLab does not rename either.

## Installation

### The latest release

This package is published to Packagist and is installed through composer with the following command:

```bash
composer require csun-metalab/laravel-multiple-logs:"^2.0"
```

Laravel discovers the service provider automatically. Import the facade classes
where you use them; global facade aliases are not registered by this package.

If you have disabled package discovery, add the provider to the existing array in
`bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    CSUNMetaLab\MultipleLogs\Providers\LoggingServiceProvider::class,
];
```

Keep any other providers already present in your application.

## Configuration

The package works without publishing its configuration. To customize it:

```bash
php artisan vendor:publish --tag=multiple-logs-config
```

This creates `config/multiple-logs.php`. It does not publish or replace Laravel's
`config/logging.php`, or change Laravel's default log channel.

Optional environment settings:

```dotenv
MULTIPLE_LOGS_DRIVER=single
MULTIPLE_LOGS_LEVEL=debug
MULTIPLE_LOGS_DAYS=7

# Optional absolute paths:
# MULTIPLE_LOGS_AUDIT_PATH=/var/log/my-app/audit.log
# MULTIPLE_LOGS_AUTH_PATH=/var/log/my-app/auth.log
```

| Configuration key | Default | Purpose |
| --- | --- | --- |
| `driver` | `single` | `single`, `daily`, `syslog`, or `errorlog` |
| `level` | `LOG_LEVEL`, or `debug` if unset | Minimum severity; `MULTIPLE_LOGS_LEVEL` takes precedence |
| `days` | `LOG_MAX_DAYS`, or `7` if unset | Daily file retention; `MULTIPLE_LOGS_DAYS` takes precedence |
| `audit_path` | `storage_path('logs/audit.log')` | Audit output path for file drivers |
| `auth_path` | `storage_path('logs/auth.log')` | Authentication output path for file drivers |
| `permission` | `0644` | File permissions for the file drivers; use an octal PHP integer |
| `locking` | `false` | Whether file writes acquire an exclusive lock |

With `single`, messages go to `audit.log` and `auth.log`. With `daily`, filenames
include the date, such as `audit-2026-10-22.log`. Monolog retains up to `days`
dated files per logger and cleans up during rotation; `0` keeps all rotated
files. Single files do not rotate automatically.

With `syslog` or `errorlog`, Laravel sends messages to the corresponding system
destination; the file paths and daily retention setting do not apply. Messages
retain their `audit` or `auth` channel name. System destinations and permissions
depend on the application's environment.

`LOG_CHANNEL` controls Laravel's default channel, independently of this package.
The package does not read the old `app.log`, `app.log_level`, or
`logging.max_days` keys.

Environment variables are read in the configuration file. After configuration
changes, rebuild any cached configuration and restart long-running workers:

```bash
php artisan config:cache
```

Invalid driver names, log levels, or daily retention values are rejected.
Laravel's normal emergency logger behavior still applies to other channel
construction failures; file write failures may throw exceptions.

## Usage

### Facades

```php
use CSUNMetaLab\MultipleLogs\Facades\AuditLog;
use CSUNMetaLab\MultipleLogs\Facades\AuthLog;

AuditLog::info('Purchase {purchase_id} completed', [
    'purchase_id' => 123,
    'user_id' => 42,
]);

AuthLog::warning('Authentication failed', [
    'user_id' => 42,
]);
```

### Helpers

```php
auditLogger()->info('User changed a record', ['record_id' => 123]);
authLogger()->error('Authentication provider unavailable');
```

Both loggers support `debug`, `info`, `notice`, `warning`, `error`, `critical`,
`alert`, `emergency`, and `log($level, $message, $context)`.

### Dependency injection

```php
use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;

public function store(AuditLogger $audit)
{
    $audit->info('Record stored', ['record_id' => 123]);
}
```

The facade, helper, and type binding resolve the same instance within a Laravel
request/job scope. Laravel resets scoped bindings between Octane requests and
queue-worker jobs.

### Context

Pass context with each message, or use `withContext()` and `withoutContext()` on
the individual logger:

```php
AuditLog::withContext(['request_id' => $requestId]);
AuditLog::info('Record stored');
AuditLog::withoutContext();
```

Laravel shared log context is copied when a package logger is constructed.
Later calls to `Log::shareContext()` do not update already constructed package
loggers; use per-message context or update each logger explicitly in that case.

These are dedicated logger instances, not the native log manager. Manager methods
such as `channel()`, `stack()`, and `build()` belong on Laravel's `Log` facade.
The package does not register `Log::channel('audit')` or
`Log::channel('auth')` configuration entries.

## Creating custom loggers

The `Logger($path, $channel, $logLevel = null)` constructor remains available for
custom subclasses inside a bootstrapped Laravel application:

```php
<?php

namespace App\Logging;

use CSUNMetaLab\MultipleLogs\Loggers\Logger;

class PurchaseLogger extends Logger
{
    public function __construct($path, $logLevel = null)
    {
        parent::__construct($path, 'purchase', $logLevel);
    }
}
```

```php
use App\Logging\PurchaseLogger;

$logger = new PurchaseLogger(storage_path('logs/purchases.log'));
$logger->info('Purchase completed', ['purchase_id' => 123]);
```

A null level uses `multiple-logs.level`. An explicit constructor level overrides
that setting. The constructor accepts PSR log-level names, Monolog integer
levels, and Monolog 3 `Level` enum values. Custom loggers use the package's driver,
retention, permission, and locking settings.

Laravel's native `Log::build()` and configured channels remain alternatives when
you need a different driver per logger or advanced channel customization.

## Upgrading from 1.x

This is a major-version migration; 2.x targets Laravel 12 and PHP 8.2+.

1. Upgrade the application to Laravel 12 and PHP 8.2 or later before installing
   this package version.
2. Keep the Composer name and `CSUNMetaLab\MultipleLogs` namespace. Existing
   facade/helper names and the custom logger constructor argument order remain.
3. Use package discovery, or move manual provider registration to
   `bootstrap/providers.php` in a modern Laravel application. Do not register
   the provider twice. Replace reliance on global facade aliases with explicit
   imports, or retain your own application-managed aliases.
4. Publish `multiple-logs.php` and migrate the settings:

   | Old setting | New setting |
   | --- | --- |
   | `app.log` | `multiple-logs.driver` / `MULTIPLE_LOGS_DRIVER` |
   | `app.log_level` | `multiple-logs.level` / `MULTIPLE_LOGS_LEVEL` |
   | `logging.max_days` | `multiple-logs.days` / `MULTIPLE_LOGS_DAYS` |
   | `LOG_MAX_DAYS` | Still accepted as a fallback; prefer `MULTIPLE_LOGS_DAYS` |

5. The default driver is now explicitly `single`; set
   `MULTIPLE_LOGS_DRIVER=daily` if you previously used daily logs. The package
   does not inherit Laravel's default channel. Default minimum severity now
   follows package configuration rather than a hardcoded constructor default.
6. Keep Laravel 12's full `config/logging.php`. If the old package configuration
   replaced it with a file containing only `max_days`, restore Laravel 12's
   logging configuration and reapply your application's channel settings.
7. Custom subclasses can no longer call the removed `Writer` methods
   `useFiles()`, `useDailyFiles()`, `useSyslog()`, or `useErrorLog()`.
   Configure the package or use Laravel's channel APIs instead.
8. Check log output after migration: formatting follows Laravel 12, context
   placeholders are interpolated, and messages emit Laravel `MessageLogged`
   events. Minimum log levels may suppress previously recorded messages.
9. Rebuild cached configuration and restart workers.

## Testing

From the package directory:

```bash
composer install
composer validate --strict
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
composer test
```

The PHPUnit suite uses Orchestra Testbench 10 and covers file separation, all
standard levels, filtering, context, custom subclasses, daily retention,
system-handler construction, invalid configuration, scoped instances, log events,
configuration publishing, and provider discovery metadata.

The GitHub Actions workflow also installs this package into a fresh Laravel 12
application and exercises automatic discovery, publishing, and
`php artisan config:cache`.

For the same smoke check locally, use a fresh Laravel 12 application with this
checkout installed through the path repository above. From the application
directory, replace the package path in these commands:

```bash
export MULTIPLE_LOGS_DRIVER=daily
export MULTIPLE_LOGS_LEVEL=warning
export MULTIPLE_LOGS_DAYS=2

php artisan config:clear
php artisan vendor:publish --tag=multiple-logs-config
php /absolute/path/to/laravel-multiple-logs/tests/smoke.php "$PWD" uncached

php artisan config:cache
unset MULTIPLE_LOGS_DRIVER MULTIPLE_LOGS_LEVEL MULTIPLE_LOGS_DAYS
php /absolute/path/to/laravel-multiple-logs/tests/smoke.php "$PWD" cached

# Remove the smoke-test configuration cache when finished.
php artisan config:clear
```

The smoke script writes test messages to the fresh application's logs. System
driver tests check handler construction; verify actual syslog/errorlog delivery
on your deployment environment.

## License

MIT. See [LICENSE](LICENSE).
