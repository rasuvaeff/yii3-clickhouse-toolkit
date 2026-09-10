<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit;

/**
 * Reads a configuration value from the process environment, then from the
 * superglobals a `.env` loader populates.
 *
 * `getenv()` alone is not enough. `vlucas/phpdotenv`'s `createImmutable()` — the
 * variant the library recommends — deliberately does not call `putenv()`: it
 * writes to `$_ENV` and `$_SERVER` only. Reading through `getenv()` therefore
 * sees nothing on a plain PHP-FPM or CLI deployment that relies on the `.env`
 * file (a `php yii some:command` cron entry is the typical case), and the
 * package silently falls back to its defaults instead of reporting a
 * configuration error. It happens to work under Docker Compose, where
 * `env_file:` puts the values into the container's process environment.
 *
 * Precedence is unchanged where `getenv()` already answers, so the fallback only
 * covers what previously fell through to the defaults.
 *
 * An empty string counts as "not set" and moves to the next source — matching
 * the `getenv($k) ?: $default` idiom this replaces. `"0"` does not: it is a
 * meaningful value for the boolean settings, and treating it as absent would
 * make `CLICKHOUSE_SECURE=0` unreadable.
 *
 * @internal
 */
final readonly class EnvironmentReader
{
    public function read(string $key, string $default = ''): string
    {
        foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
