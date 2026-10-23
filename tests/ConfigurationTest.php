<?php

namespace CSUNMetaLab\MultipleLogs\Tests;

use CSUNMetaLab\MultipleLogs\Providers\LoggingServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

class ConfigurationTest extends TestCase
{
    public function test_defaults_are_merged_without_overwriting_user_settings(): void
    {
        config(['multiple-logs' => ['level' => 'error']]);
        (new LoggingServiceProvider($this->app))->register();

        self::assertSame('error', config('multiple-logs.level'));
        self::assertSame('single', config('multiple-logs.driver'));
        self::assertSame(storage_path('logs/audit.log'), config('multiple-logs.audit_path'));
        self::assertSame(storage_path('logs/auth.log'), config('multiple-logs.auth_path'));
    }

    public function test_publishing_even_with_force_preserves_native_logging_configuration(): void
    {
        $configDirectory = $this->temporaryDirectory.'/config';
        mkdir($configDirectory);
        $native = "<?php\nreturn ['default' => 'single', 'sentinel' => true];\n";
        file_put_contents($configDirectory.'/logging.php', $native);

        $this->app->useConfigPath($configDirectory);
        (new LoggingServiceProvider($this->app))->boot();

        $this->artisan('vendor:publish', [
            '--tag' => 'multiple-logs-config',
            '--force' => true,
        ])->assertExitCode(0);

        self::assertSame($native, file_get_contents($configDirectory.'/logging.php'));
        self::assertFileExists($configDirectory.'/multiple-logs.php');
        self::assertSame(
            file_get_contents(__DIR__.'/../src/MultipleLogs/config/multiple-logs.php'),
            file_get_contents($configDirectory.'/multiple-logs.php')
        );
    }

    public function test_laravel_discovers_the_provider_from_composer_metadata(): void
    {
        $base = $this->temporaryDirectory.'/discovery';
        mkdir($base.'/vendor/composer', 0777, true);
        file_put_contents($base.'/composer.json', '{}');

        $composer = json_decode(file_get_contents(__DIR__.'/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
        file_put_contents($base.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['name' => $composer['name'], 'extra' => $composer['extra']],
            ],
        ], JSON_THROW_ON_ERROR));

        $manifest = new PackageManifest(new Filesystem(), $base, $base.'/packages.php');
        $manifest->build();

        self::assertContains(LoggingServiceProvider::class, $manifest->providers());
    }
}
