<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit;

use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;

/**
 * Builds a {@see ClickHouseConfig} from a plain parameters array (the
 * `rasuvaeff/yii3-clickhouse-toolkit` params key). Values are coerced to their
 * target types so environment strings (`CLICKHOUSE_*`) are accepted as-is; the
 * `secure` flag understands textual booleans (`"false"`, `"0"`, `"off"` → false).
 *
 * Range/emptiness validation is delegated to {@see ClickHouseConfig}.
 *
 * @api
 */
final readonly class ClickHouseConfigFactory
{
    private const array TRUTHY_STRINGS = ['1', 'true', 'on', 'yes'];

    /**
     * @param array<string, mixed> $params
     */
    public function fromParams(array $params): ClickHouseConfig
    {
        return new ClickHouseConfig(
            host: (string) ($params['host'] ?? '127.0.0.1'),
            port: (int) ($params['port'] ?? 8123),
            database: (string) ($params['database'] ?? 'default'),
            username: (string) ($params['username'] ?? 'default'),
            password: (string) ($params['password'] ?? ''),
            secure: $this->toBool($params['secure'] ?? false),
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), self::TRUTHY_STRINGS, true);
        }

        return (bool) $value;
    }
}
