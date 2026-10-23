# Changelog

## 2.0.0 - Unreleased

### Changed

- Target Laravel 12, PHP 8.2+, and Monolog 3 with explicit Composer dependencies.
- Replace the removed Illuminate Log Writer with an Illuminate Logger using
  Laravel's native on-demand channel construction.
- Preserve the public facade/helper names, logger classes, container aliases,
  and custom logger constructor argument order.
- Resolve facade, helper, and type bindings to the same scoped instance.
- Read driver, level, retention, and file settings from `multiple-logs.php`.
- Follow Laravel 12 formatting, interpolate context placeholders, and dispatch
  Laravel log events once per written message.
- Convert autoloading to PSR-4 and correct facade documentation.

### Added

- Laravel provider auto-discovery and a `multiple-logs-config` publishing tag.
- Configurable audit/authentication paths, permissions, and file locking.
- Configuration validation for driver, level, and daily retention.
- Integration tests, a fresh-application smoke script, and GitHub Actions.
- Updated installation instructions and a 1.x migration guide.

### Removed

- Laravel 5 / PHP 5 support and the obsolete Writer setup methods.
- Publishing to Laravel's own `config/logging.php`.
- Reading `app.log`, `app.log_level`, and `logging.max_days`.

`LOG_MAX_DAYS` remains a fallback when `MULTIPLE_LOGS_DAYS` is unset.
This release is a migration candidate pending execution of the included tests.
