<?php

namespace CSUNMetaLab\MultipleLogs\Facades;

use CSUNMetaLab\MultipleLogs\Loggers\AuthenticationLogger;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin AuthenticationLogger
 * @see AuthenticationLogger
 */
class AuthLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auth_log';
    }
}
