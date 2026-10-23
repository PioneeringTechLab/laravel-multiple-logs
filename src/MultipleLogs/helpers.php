<?php

use CSUNMetaLab\MultipleLogs\Loggers\AuditLogger;
use CSUNMetaLab\MultipleLogs\Loggers\AuthenticationLogger;

if (! function_exists('auditLogger')) {
    function auditLogger(): AuditLogger
    {
        return app('audit_log');
    }
}

if (! function_exists('authLogger')) {
    function authLogger(): AuthenticationLogger
    {
        return app('auth_log');
    }
}
