<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory;
use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface;
use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;

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

    ClickHouseClientFactory::class => static fn (
        ClickHouseConfig $clickHouseConfig,
        ContainerInterface $container,
    ): ClickHouseClientFactory => new ClickHouseClientFactory(
        config: $clickHouseConfig,
        httpClient: $container->has(ClientInterface::class) ? $container->get(ClientInterface::class) : null,
        requestFactory: $container->has(RequestFactoryInterface::class) ? $container->get(RequestFactoryInterface::class) : null,
        streamFactory: $container->has(StreamFactoryInterface::class) ? $container->get(StreamFactoryInterface::class) : null,
        uriFactory: $container->has(UriFactoryInterface::class) ? $container->get(UriFactoryInterface::class) : null,
    ),

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
];
