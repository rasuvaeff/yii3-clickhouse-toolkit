<?php

declare(strict_types=1);

use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsGenerateCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsRunCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsStatusCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrations:generate' => ClickHouseMigrationsGenerateCommand::class,
            'clickhouse:migrations:status' => ClickHouseMigrationsStatusCommand::class,
            'clickhouse:migrations:migrate' => ClickHouseMigrationsRunCommand::class,
        ],
    ],
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        'host' => getenv('CLICKHOUSE_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CLICKHOUSE_PORT') ?: 8123),
        'database' => getenv('CLICKHOUSE_DB') ?: 'default',
        'username' => getenv('CLICKHOUSE_USER') ?: 'default',
        'password' => getenv('CLICKHOUSE_PASSWORD') ?: '',
        'secure' => filter_var(getenv('CLICKHOUSE_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'migrationsPath' => getenv('CLICKHOUSE_MIGRATIONS_PATH') ?: '',
    ],
];
