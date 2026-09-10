<?php

declare(strict_types=1);

use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsGenerateCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsRunCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsStatusCommand;
use Rasuvaeff\Yii3ClickHouseToolkit\EnvironmentReader;

$env = new EnvironmentReader();

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrations:generate' => ClickHouseMigrationsGenerateCommand::class,
            'clickhouse:migrations:status' => ClickHouseMigrationsStatusCommand::class,
            'clickhouse:migrations:migrate' => ClickHouseMigrationsRunCommand::class,
        ],
    ],
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        // Read through EnvironmentReader, not getenv(): a `.env` loaded with
        // Dotenv::createImmutable() never reaches getenv(), so these would all
        // silently fall back to the defaults below outside Docker Compose.
        'host' => $env->read('CLICKHOUSE_HOST', '127.0.0.1'),
        'port' => (int) $env->read('CLICKHOUSE_PORT', '8123'),
        'database' => $env->read('CLICKHOUSE_DB', 'default'),
        'username' => $env->read('CLICKHOUSE_USER', 'default'),
        'password' => $env->read('CLICKHOUSE_PASSWORD'),
        'secure' => filter_var($env->read('CLICKHOUSE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'migrationsPath' => $env->read('CLICKHOUSE_MIGRATIONS_PATH'),
        // PSR-4 namespace of the migrations directory, resolved through
        // Composer's map when 'migrationsPath' is empty. Deliberately not read
        // from the environment, unlike every key above it: the namespace is a
        // property of the code, identical on every stand, and the point of the
        // parameter is to stop the directory from being a per-stand `.env` line
        // that no local run can validate. Example:
        // 'migrationsNamespace' => 'App\\Infrastructure\\ClickHouse\\Migration'.
        'migrationsNamespace' => '',
        // Bookkeeping table the runner records applied migrations in. A name
        // other than the default is what lets the package be adopted alongside
        // an existing `_migrations` of a different schema, and what keeps two
        // applications on one ClickHouse database apart.
        'migrationsTable' => $env->read('CLICKHOUSE_MIGRATIONS_TABLE', '_migrations'),
        // `{{key}}` tokens replaced in every migration file before it is hashed
        // and executed — how a package's shipped DDL learns the table name the
        // application configured
        'migrationPlaceholders' => [],
    ],
];
