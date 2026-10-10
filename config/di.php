<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory;
use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;
use Rasuvaeff\ClickHouseToolkit\ContextClickHouseClient;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder;
use Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager;
use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;
use Rasuvaeff\Yii3ClickHouseToolkit\MigrationsDirectoryResolver;
use Rasuvaeff\Context\Context;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use Yiisoft\Definitions\Reference;

/** @var array $params */

$config = $params['rasuvaeff/yii3-clickhouse-toolkit'] ?? [];
$config = is_array($config) ? $config : [];

$migrationsPath = static function () use ($config): string {
    $path = $config['migrationsPath'] ?? '';

    // An explicit path wins: applications already configured this way keep
    // working unchanged, and it stays the escape hatch for a migrations
    // directory that lives outside the PSR-4 tree.
    if (is_string($path) && $path !== '') {
        return $path;
    }

    $namespace = $config['migrationsNamespace'] ?? '';

    if (is_string($namespace) && $namespace !== '') {
        return (new MigrationsDirectoryResolver())->resolve($namespace);
    }

    throw new RuntimeException(
        'ClickHouse migrations path is not configured. Set the CLICKHOUSE_MIGRATIONS_PATH '
        . 'environment variable, or override the '
        . '"rasuvaeff/yii3-clickhouse-toolkit" => "migrationsPath" parameter, or point '
        . '"migrationsNamespace" at the PSR-4 namespace of the migrations directory.',
    );
};

$migrationsTable = static function () use ($config): string {
    $table = $config['migrationsTable'] ?? '_migrations';

    // Validation itself belongs to the runner (the name is interpolated into
    // SQL, so it asserts a plain identifier); this only keeps a malformed param
    // type from reaching a string argument.
    return is_string($table) && $table !== '' ? $table : '_migrations';
};

$migrationPlaceholders = static function () use ($config): array {
    $placeholders = $config['migrationPlaceholders'] ?? [];

    if (!is_array($placeholders)) {
        return [];
    }

    $resolved = [];

    /** @var mixed $value */
    foreach ($placeholders as $key => $value) {
        if (is_string($key) && (is_string($value) || is_int($value))) {
            $resolved[$key] = (string) $value;
        }
    }

    return $resolved;
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
    // Applications can replace this default with a request/job context in
    // their own DI config; the background context keeps existing installs
    // working when no execution budget is supplied.
    Context::class => static fn (): Context => Context::background(),
    ContextClickHouseClient::class => static fn (PsrClickHouseClient $client, Context $context): ContextClickHouseClient => new ContextClickHouseClient(
        client: $client,
        context: $context,
    ),
    ClickHouseClient::class => ContextClickHouseClient::class,

    ClickHouseMigrationRunner::class => static fn (ClickHouseClient $client): ClickHouseMigrationRunner => new ClickHouseMigrationRunner(
        client: $client,
        migrationsPath: $migrationsPath(),
        placeholders: $migrationPlaceholders(),
        migrationsTable: $migrationsTable(),
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
