<?php

namespace CSUNMetaLab\MultipleLogs\Loggers;

use Illuminate\Log\Logger as IlluminateLogger;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class Logger extends IlluminateLogger
{
    /**
     * Keep the original path/channel/level constructor for custom loggers.
     * A null level uses the package configuration.
     */
    public function __construct(string $path, string $channel, Level|string|int|null $logLevel = null)
    {
        $driver = config('multiple-logs.driver', 'single');

        if (! in_array($driver, ['single', 'daily', 'syslog', 'errorlog'], true)) {
            throw new InvalidArgumentException(
                'The multiple-logs.driver setting must be single, daily, syslog, or errorlog.'
            );
        }

        // Validate before LogManager can fall back to Laravel's emergency log.
        $level = MonologLogger::toMonologLevel(
            $logLevel ?? config('multiple-logs.level', 'debug')
        );

        $configuration = [
            'driver' => $driver,
            'name' => $channel,
            'path' => $path,
            'level' => $level->toPsrLogLevel(),
            'replace_placeholders' => true,
            'permission' => config('multiple-logs.permission', 0644),
            'locking' => config('multiple-logs.locking', false),
        ];

        if ($driver === 'daily') {
            $days = filter_var(config('multiple-logs.days', 7), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);

            if ($days === false) {
                throw new InvalidArgumentException(
                    'The multiple-logs.days setting must be a non-negative integer.'
                );
            }

            $configuration['days'] = $days;
        }

        $manager = app('log');
        $logger = $manager->build($configuration);

        // Reuse Laravel's handlers, formatting, processors, and event dispatcher.
        // Unwrap the temporary Illuminate logger so each message emits one event.
        parent::__construct($logger->getLogger(), $logger->getEventDispatcher());

        $this->withContext($manager->sharedContext());
    }
}
