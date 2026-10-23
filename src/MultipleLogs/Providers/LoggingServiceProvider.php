<?php

namespace CSUNMetaLab\MultipleLogs\Providers;

use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;
use CSUNMetaLab\MultipleLogs\Loggers\AuthenticationLogger;
use Illuminate\Support\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/multiple-logs.php', 'multiple-logs');

        // Share instances within a request/job; Laravel flushes scoped bindings
        // when starting a new Octane request or queue-worker job.
        $this->app->scoped('audit_log', function ($app) {
            return new AuditLogger($app['config']->get('multiple-logs.audit_path'));
        });

        $this->app->scoped('auth_log', function ($app) {
            return new AuthenticationLogger($app['config']->get('multiple-logs.auth_path'));
        });

        $this->app->alias('audit_log', AuditLogger::class);
        $this->app->alias('auth_log', AuthenticationLogger::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/multiple-logs.php' => config_path('multiple-logs.php'),
            ], 'multiple-logs-config');
        }
    }
}
