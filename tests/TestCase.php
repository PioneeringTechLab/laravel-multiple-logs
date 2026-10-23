<?php

namespace CSUNMetaLab\MultipleLogs\Tests;

use CSUNMetaLab\MultipleLogs\Providers\LoggingServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/multiple-logs-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0777, true);

        parent::setUp();

        config([
            'multiple-logs.driver' => 'single',
            'multiple-logs.level' => 'debug',
            'multiple-logs.days' => 7,
            'multiple-logs.audit_path' => $this->temporaryDirectory.'/audit.log',
            'multiple-logs.auth_path' => $this->temporaryDirectory.'/auth.log',
            'logging.default' => 'single',
            'logging.channels.single' => [
                'driver' => 'single',
                'path' => $this->temporaryDirectory.'/laravel.log',
                'level' => 'debug',
            ],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [LoggingServiceProvider::class];
    }

    protected function tearDown(): void
    {
        try {
            foreach (['audit_log', 'auth_log'] as $binding) {
                if ($this->app !== null && $this->app->resolved($binding)) {
                    $this->app->make($binding)->getLogger()->close();
                }
            }

            if ($this->app !== null && $this->app->resolved('log')) {
                foreach ($this->app->make('log')->getChannels() as $logger) {
                    $logger->getLogger()->close();
                }
            }
        } finally {
            parent::tearDown();
            (new Filesystem())->deleteDirectory($this->temporaryDirectory);
        }
    }
}
