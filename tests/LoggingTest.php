<?php

namespace CSUNMetaLab\MultipleLogs\Tests;

use CSUNMetaLab\MultipleLogs\Facades\AuditLog;
use CSUNMetaLab\MultipleLogs\Facades\AuthLog;
use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;
use CSUNMetaLab\MultipleLogs\Loggers\AuthenticationLogger;
use CSUNMetaLab\MultipleLogs\Loggers\Logger;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;

class LoggingTest extends TestCase
{
    public function test_facades_and_helpers_write_to_separate_files(): void
    {
        AuditLog::info('audit-facade');
        auditLogger()->error('audit-helper');
        AuthLog::info('auth-facade');
        authLogger()->error('auth-helper');
        Log::info('native-log');

        $audit = file_get_contents($this->temporaryDirectory.'/audit.log');
        $auth = file_get_contents($this->temporaryDirectory.'/auth.log');
        $native = file_get_contents($this->temporaryDirectory.'/laravel.log');

        self::assertStringContainsString('audit-facade', $audit);
        self::assertStringContainsString('audit-helper', $audit);
        self::assertStringNotContainsString('auth-', $audit);
        self::assertStringContainsString('auth-facade', $auth);
        self::assertStringContainsString('auth-helper', $auth);
        self::assertStringNotContainsString('audit-', $auth);
        self::assertStringContainsString('native-log', $native);
        self::assertStringNotContainsString('audit-', $native);
        self::assertStringNotContainsString('auth-', $native);
    }

    public function test_facades_helpers_and_type_resolution_share_a_scoped_instance(): void
    {
        self::assertInstanceOf(AuditLogger::class, auditLogger());
        self::assertInstanceOf(AuthenticationLogger::class, authLogger());
        self::assertSame(auditLogger(), AuditLog::getFacadeRoot());
        self::assertSame(authLogger(), AuthLog::getFacadeRoot());
        self::assertSame(auditLogger(), app(AuditLogger::class));
        self::assertSame(authLogger(), app(AuthenticationLogger::class));
    }

    #[DataProvider('levels')]
    public function test_standard_logging_methods_and_log_method(string $level): void
    {
        auditLogger()->{$level}('method-'.$level);
        authLogger()->log($level, 'generic-'.$level);

        self::assertStringContainsString(
            'method-'.$level,
            file_get_contents($this->temporaryDirectory.'/audit.log')
        );
        self::assertStringContainsString(
            'generic-'.$level,
            file_get_contents($this->temporaryDirectory.'/auth.log')
        );
    }

    public static function levels(): array
    {
        return array_map(fn ($level) => [$level], [
            'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency',
        ]);
    }

    public function test_configured_level_filters_messages_and_context_is_preserved(): void
    {
        config(['multiple-logs.level' => 'warning']);

        AuditLog::info('filtered-audit');
        AuthLog::debug('filtered-auth');
        AuditLog::warning('User {user_id} changed a record', ['user_id' => 42, 'action' => 'update']);
        AuthLog::error('allowed-auth');

        $audit = file_get_contents($this->temporaryDirectory.'/audit.log');
        $auth = file_get_contents($this->temporaryDirectory.'/auth.log');

        self::assertStringNotContainsString('filtered-audit', $audit);
        self::assertStringContainsString('User 42 changed a record', $audit);
        self::assertStringContainsString('"action":"update"', $audit);
        self::assertStringNotContainsString('filtered-auth', $auth);
        self::assertStringContainsString('allowed-auth', $auth);
    }

    public function test_custom_logger_constructor_and_explicit_level_are_preserved(): void
    {
        config(['multiple-logs.level' => 'emergency']);

        $logger = new class($this->temporaryDirectory.'/purchases.log', 'warning') extends Logger {
            public function __construct($path, $logLevel = 'debug')
            {
                parent::__construct($path, 'purchase', $logLevel);
            }
        };

        try {
            $logger->info('filtered-purchase');
            $logger->warning('purchase-complete', ['purchase_id' => 123]);
            $contents = file_get_contents($this->temporaryDirectory.'/purchases.log');

            self::assertStringNotContainsString('filtered-purchase', $contents);
            self::assertStringContainsString('purchase.WARNING', $contents);
            self::assertStringContainsString('purchase-complete', $contents);
            self::assertStringContainsString('"purchase_id":123', $contents);
        } finally {
            $logger->getLogger()->close();
        }
    }

    public function test_monolog_level_enum_is_accepted_in_constructor(): void
    {
        $logger = new AuditLogger($this->temporaryDirectory.'/enum.log', Level::Error);

        try {
            $logger->warning('filtered-enum');
            $logger->error('allowed-enum');
            $contents = file_get_contents($this->temporaryDirectory.'/enum.log');

            self::assertStringNotContainsString('filtered-enum', $contents);
            self::assertStringContainsString('allowed-enum', $contents);
        } finally {
            $logger->getLogger()->close();
        }
    }

    public function test_daily_driver_uses_dated_files_and_removes_old_rotated_files(): void
    {
        config(['multiple-logs.driver' => 'daily', 'multiple-logs.days' => 2]);

        $yesterday = $this->temporaryDirectory.'/audit-'.date('Y-m-d', strtotime('-1 day')).'.log';
        $older = $this->temporaryDirectory.'/audit-'.date('Y-m-d', strtotime('-2 days')).'.log';
        file_put_contents($yesterday, 'yesterday');
        file_put_contents($older, 'older');

        AuditLog::info('today-audit');
        AuthLog::info('today-auth');
        auditLogger()->getLogger()->close();
        authLogger()->getLogger()->close();

        self::assertStringContainsString(
            'today-audit',
            file_get_contents($this->temporaryDirectory.'/audit-'.date('Y-m-d').'.log')
        );
        self::assertFileExists($this->temporaryDirectory.'/auth-'.date('Y-m-d').'.log');
        self::assertFileExists($yesterday);
        self::assertFileDoesNotExist($older);
        self::assertFileDoesNotExist($this->temporaryDirectory.'/audit.log');
    }

    public function test_daily_retention_zero_keeps_old_files(): void
    {
        config(['multiple-logs.driver' => 'daily', 'multiple-logs.days' => 0]);
        $old = $this->temporaryDirectory.'/audit-2000-01-01.log';
        file_put_contents($old, 'old');

        AuditLog::info('keep-all');
        auditLogger()->getLogger()->close();

        self::assertFileExists($old);
    }

    #[DataProvider('systemDrivers')]
    public function test_system_drivers_construct_the_correct_handler(string $driver, string $handler): void
    {
        config(['multiple-logs.driver' => $driver]);

        self::assertInstanceOf($handler, auditLogger()->getLogger()->getHandlers()[0]);
        self::assertInstanceOf($handler, authLogger()->getLogger()->getHandlers()[0]);
        self::assertFileDoesNotExist($this->temporaryDirectory.'/audit.log');
    }

    public static function systemDrivers(): array
    {
        return [
            ['syslog', SyslogHandler::class],
            ['errorlog', ErrorLogHandler::class],
        ];
    }

    public function test_invalid_driver_fails_instead_of_using_the_default_log(): void
    {
        config(['multiple-logs.driver' => 'stack']);

        $this->expectException(InvalidArgumentException::class);
        auditLogger();
    }

    public function test_invalid_level_fails_instead_of_using_the_default_log(): void
    {
        config(['multiple-logs.level' => 'not-a-level']);

        $this->expectException(InvalidArgumentException::class);
        auditLogger();
    }

    #[DataProvider('invalidRetention')]
    public function test_invalid_daily_retention_is_rejected(mixed $days): void
    {
        config(['multiple-logs.driver' => 'daily', 'multiple-logs.days' => $days]);

        $this->expectException(InvalidArgumentException::class);
        auditLogger();
    }

    public static function invalidRetention(): array
    {
        return [[-1], ['invalid'], [1.5]];
    }

    public function test_a_message_emits_exactly_one_laravel_event(): void
    {
        $events = [];
        app('events')->listen(MessageLogged::class, function (MessageLogged $event) use (&$events) {
            if ($event->message === 'event-test') {
                $events[] = $event;
            }
        });

        AuditLog::warning('event-test', ['user_id' => 42]);

        self::assertCount(1, $events);
        self::assertSame('warning', $events[0]->level);
        self::assertSame(['user_id' => 42], $events[0]->context);
    }

    public function test_context_is_shared_within_a_scope_and_cleared_for_a_new_scope(): void
    {
        $first = auditLogger();
        AuditLog::withContext(['request_id' => 'first-request']);
        auditLogger()->info('first-message');
        $first->getLogger()->close();

        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();

        $second = auditLogger();
        AuditLog::info('second-message');

        self::assertNotSame($first, $second);
        $lines = file($this->temporaryDirectory.'/audit.log');
        self::assertStringContainsString('first-request', $lines[0]);
        self::assertStringContainsString('second-message', $lines[1]);
        self::assertStringNotContainsString('first-request', $lines[1]);
    }

    public function test_existing_shared_context_is_copied_when_a_logger_is_created(): void
    {
        Log::shareContext(['trace_id' => 'existing-trace']);

        AuditLog::info('context-audit');
        AuthLog::info('context-auth');

        self::assertStringContainsString(
            'existing-trace',
            file_get_contents($this->temporaryDirectory.'/audit.log')
        );
        self::assertStringContainsString(
            'existing-trace',
            file_get_contents($this->temporaryDirectory.'/auth.log')
        );
    }
}
