<?php

namespace CSUNMetaLab\MultipleLogs\Loggers;

use Monolog\Level;

class AuditLogger extends Logger
{
    public function __construct(string $path, Level|string|int|null $logLevel = null)
    {
        parent::__construct($path, 'audit', $logLevel);
    }
}
