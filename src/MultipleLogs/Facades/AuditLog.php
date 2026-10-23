<?php

namespace CSUNMetaLab\MultipleLogs\Facades;

use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin AuditLogger
 * @see AuditLogger
 */
class AuditLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'audit_log';
    }
}
