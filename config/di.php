<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory;
use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder;
use Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager;
use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use Yiisoft\Definitions\Reference;

/** @var array $params */

$config = $params['rasuvaeff/yii3-clickhouse-toolkit'] ?? [];
$config = is_array($config) ? $config : [];

$migrationsPath = static function () use ($config): string {
    $path = $config['migrationsPath'] ?? '';

    if (!is_string($path) || $path === '') {
        throw new RuntimeException(
            'ClickHouse migrations path is not configured. Set the CLICKHOUSE_MIGRATIONS_PATH '
            . 'environment variable, or override the '
            . '"rasuvaeff/yii3-clickhouse-toolkit" => "migrationsPath" parameter.',
        );
    }

    return $path;
};

return [
    ClickHouseConfig::class => static fn (): ClickHouseConfig => (new ClickHouseConfigFactory())->fromParams($config),

    ClickHouseClientFactory::class => [
        '__construct()' => [
            'config' => Reference::to(ClickHouseConfig::class),
            'httpClient' => Reference::optional(ClientInterface::class),
            'requestFactory' => Reference::optional(RequestFactoryInterface::class),
            'streamFactory' => Reference::optional(StreamFactoryInterface::class),
            'uriFactory' => Reference::optional(UriFactoryInterface::class),
        ],
    ],

    PsrClickHouseClient::class => static fn (ClickHouseClientFactory $factory): PsrClickHouseClient => $factory->create(),
    ClickHouseClient::class => PsrClickHouseClient::class,

    ClickHouseMigrationRunner::class => static fn (ClickHouseClient $client): ClickHouseMigrationRunner => new ClickHouseMigrationRunner(
        client: $client,
        migrationsPath: $migrationsPath(),
    ),
    ClickHouseMigrationRunnerInterface::class => ClickHouseMigrationRunner::class,

    ClickHouseMigrationGenerator::class => static fn (): ClickHouseMigrationGenerator => new ClickHouseMigrationGenerator(
        $migrationsPath(),
    ),

    ClickHouseMutationBuilder::class => static fn (ClickHouseClient $client): ClickHouseMutationBuilder => new ClickHouseMutationBuilder(
        client: $client,
    ),

    ClickHousePartitionManager::class => static fn (ClickHouseClient $client): ClickHousePartitionManager => new ClickHousePartitionManager(
        client: $client,
    ),
];
