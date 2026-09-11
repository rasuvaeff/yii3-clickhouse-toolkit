<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit\Tests;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory;
use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface;
use Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder;
use Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsGenerateCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsRunCommand;
use Rasuvaeff\ClickHouseToolkit\Command\ClickHouseMigrationsStatusCommand;
use ReflectionProperty;
use RuntimeException;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Data\DataProvider;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function bindsClickHouseConfigFromParams(): void
    {
        $config = $this->container([
            'host' => 'ch.example',
            'port' => 9000,
            'database' => 'analytics',
            'secure' => true,
        ])->get(ClickHouseConfig::class);

        Assert::instanceOf($config, ClickHouseConfig::class);
        Assert::same($config->host, 'ch.example');
        Assert::same($config->port, 9000);
        Assert::same($config->database, 'analytics');
        Assert::true($config->secure);
    }

    public function aliasesClickHouseClientToPsrImplementation(): void
    {
        $client = $this->container()->get(ClickHouseClient::class);

        Assert::instanceOf($client, PsrClickHouseClient::class);
    }

    public function fallsBackToDiscoveredPsr18ClientWhenAppBindsNone(): void
    {
        // No ClientInterface bound in the container -> the factory passes null
        // and clickhouse-toolkit discovers a PSR-18 client (guzzle, dev-dep).
        $client = $this->container()->get(PsrClickHouseClient::class);

        Assert::instanceOf($client, PsrClickHouseClient::class);
    }

    public function injectsAppBoundPsr18ClientIntoFactory(): void
    {
        // The toolkit promise: an app-configured PSR-18 client (timeouts/TLS)
        // must reach ClickHouseClientFactory. Bind one and prove it is injected.
        $recording = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('not invoked in this test');
            }
        };

        $factory = $this->container([], [ClientInterface::class => $recording])
            ->get(ClickHouseClientFactory::class);

        Assert::instanceOf($factory, ClickHouseClientFactory::class);

        $injected = (new ReflectionProperty(ClickHouseClientFactory::class, 'httpClient'))->getValue($factory);

        Assert::same($injected, $recording);
    }

    public function migrationsPathMustBeConfigured(): void
    {
        /** @var Closure(): ClickHouseMigrationGenerator $generator */
        $generator = $this->definitions(['migrationsPath' => ''])[ClickHouseMigrationGenerator::class];

        try {
            $generator();
        } catch (RuntimeException $e) {
            Assert::string($e->getMessage())->contains('CLICKHOUSE_MIGRATIONS_PATH');
            // Both ways out are named: the message is the only place an operator
            // learns the namespace parameter exists.
            Assert::string($e->getMessage())->contains('migrationsNamespace');

            return;
        }

        Assert::fail('Expected a RuntimeException when migrationsPath is empty');
    }

    /**
     * The point of the parameter: both consumers build their path from the same
     * DI closure, so resolving the namespace once has to reach the runner and
     * the generator alike. A unit test of the resolver cannot show that.
     */
    public function migrationsNamespaceResolvesThePathForBothConsumers(): void
    {
        $container = $this->container([
            'migrationsPath' => '',
            'migrationsNamespace' => 'Rasuvaeff\\Yii3ClickHouseToolkit\\Tests',
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);
        $generator = $container->get(ClickHouseMigrationGenerator::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'migrationsPath'))->getValue($runner),
            __DIR__,
        );
        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationGenerator::class, 'migrationsPath'))->getValue($generator),
            __DIR__,
        );
    }

    /**
     * Applications already configured with a path keep working unchanged, and a
     * migrations directory outside the PSR-4 tree stays reachable.
     */
    public function explicitMigrationsPathWinsOverTheNamespace(): void
    {
        $container = $this->container([
            'migrationsPath' => '/tmp/clickhouse-migrations',
            'migrationsNamespace' => 'Rasuvaeff\\Yii3ClickHouseToolkit\\Tests',
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'migrationsPath'))->getValue($runner),
            '/tmp/clickhouse-migrations',
        );
    }

    public function unresolvableMigrationsNamespaceReportsTheNamespace(): void
    {
        /** @var Closure(): ClickHouseMigrationGenerator $generator */
        $generator = $this->definitions([
            'migrationsPath' => '',
            'migrationsNamespace' => 'No\\Such\\Namespace',
        ])[ClickHouseMigrationGenerator::class];

        try {
            $generator();
        } catch (RuntimeException $e) {
            Assert::string($e->getMessage())->contains('No\\Such\\Namespace');

            return;
        }

        Assert::fail('Expected a RuntimeException when migrationsNamespace resolves to nothing');
    }

    public function resolvesMigrationServicesWhenPathIsSet(): void
    {
        $container = $this->container(['migrationsPath' => '/tmp/clickhouse-migrations']);

        Assert::instanceOf(
            $container->get(ClickHouseMigrationRunnerInterface::class),
            ClickHouseMigrationRunner::class,
        );
        Assert::instanceOf(
            $container->get(ClickHouseMigrationGenerator::class),
            ClickHouseMigrationGenerator::class,
        );
    }

    public function migrationPlaceholdersReachTheRunner(): void
    {
        // this is the path that lets a package ship DDL with {{table}} tokens:
        // params → di → runner. Without it the tokens reach ClickHouse verbatim.
        $container = $this->container([
            'migrationsPath' => __DIR__,
            'migrationPlaceholders' => ['exposures_table' => 'custom_exposures', 'ttl' => 30],
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);
        $placeholders = (new ReflectionProperty(ClickHouseMigrationRunner::class, 'placeholders'))->getValue($runner);

        Assert::same($placeholders, ['exposures_table' => 'custom_exposures', 'ttl' => '30']);
    }

    public function malformedMigrationPlaceholdersAreDropped(): void
    {
        // params come from an application's config file: a non-string key or a
        // nested array must not reach str_replace()
        $container = $this->container([
            'migrationsPath' => __DIR__,
            'migrationPlaceholders' => ['ok' => 'value', 'bad' => ['nested'], 7 => 'numeric key'],
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'placeholders'))->getValue($runner),
            ['ok' => 'value'],
        );
    }

    public function migrationsTableReachesTheRunner(): void
    {
        // The whole point of the param: adopting the package where a
        // `_migrations` of a different schema already exists needs the runner
        // pointed at a fresh name, and nothing else can do that.
        $container = $this->container([
            'migrationsPath' => __DIR__,
            'migrationsTable' => 'app_schema_migrations',
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'migrationsTable'))->getValue($runner),
            'app_schema_migrations',
        );
    }

    public function migrationsTableDefaultsToUnderscoreMigrations(): void
    {
        $container = $this->container(['migrationsPath' => __DIR__]);

        $runner = $container->get(ClickHouseMigrationRunner::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'migrationsTable'))->getValue($runner),
            '_migrations',
        );
    }

    #[DataProvider('malformedMigrationsTableProvider')]
    public function malformedMigrationsTableFallsBackToTheDefault(mixed $value): void
    {
        // params come from an application's config file; a non-string must not
        // reach a string argument. A malformed *name* is still the runner's
        // business — it asserts a plain identifier and throws.
        $container = $this->container([
            'migrationsPath' => __DIR__,
            'migrationsTable' => $value,
        ]);

        $runner = $container->get(ClickHouseMigrationRunner::class);

        Assert::same(
            (new ReflectionProperty(ClickHouseMigrationRunner::class, 'migrationsTable'))->getValue($runner),
            '_migrations',
        );
    }

    public static function malformedMigrationsTableProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'int' => [42];
        yield 'array' => [['_migrations']];
        yield 'null' => [null];
    }

    public function bindsTableOperationHelpers(): void
    {
        // MutationBuilder and PartitionManager are client-only helpers, so the
        // bridge wires them as singletons over the same live client.
        $container = $this->container();

        Assert::instanceOf(
            $container->get(ClickHouseMutationBuilder::class),
            ClickHouseMutationBuilder::class,
        );
        Assert::instanceOf(
            $container->get(ClickHousePartitionManager::class),
            ClickHousePartitionManager::class,
        );
    }

    public function registersMigrationConsoleCommands(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';

        Assert::same($params['yiisoft/yii-console']['commands'], [
            'clickhouse:migrations:generate' => ClickHouseMigrationsGenerateCommand::class,
            'clickhouse:migrations:status' => ClickHouseMigrationsStatusCommand::class,
            'clickhouse:migrations:migrate' => ClickHouseMigrationsRunCommand::class,
        ]);
    }

    public function resolvesConsoleCommandsThroughContainer(): void
    {
        $container = $this->container(['migrationsPath' => '/tmp/clickhouse-migrations']);

        Assert::instanceOf(
            $container->get(ClickHouseMigrationsGenerateCommand::class),
            ClickHouseMigrationsGenerateCommand::class,
        );
        Assert::instanceOf(
            $container->get(ClickHouseMigrationsRunCommand::class),
            ClickHouseMigrationsRunCommand::class,
        );
        Assert::instanceOf(
            $container->get(ClickHouseMigrationsStatusCommand::class),
            ClickHouseMigrationsStatusCommand::class,
        );
    }

    /**
     * @param array<string, mixed> $chOverrides
     * @param array<string, mixed> $extra
     */
    private function container(array $chOverrides = [], array $extra = []): Container
    {
        $definitions = $this->definitions($chOverrides) + $extra;

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }

    /**
     * config/params.php is not covered by cs, psalm or the source-scanning part
     * of the suite, so the only thing that catches a regression here is loading
     * the real file. The value is placed where Dotenv::createImmutable() puts it
     * and nowhere else: before the fix this read the default instead.
     */
    public function paramsReadValuesThatOnlyLiveInTheDotenvSuperglobals(): void
    {
        putenv('CLICKHOUSE_HOST');
        putenv('CLICKHOUSE_MIGRATIONS_PATH');
        putenv('CLICKHOUSE_MIGRATIONS_TABLE');
        $_ENV['CLICKHOUSE_HOST'] = 'ch.from-dotenv';
        $_SERVER['CLICKHOUSE_MIGRATIONS_PATH'] = '/srv/app/migrations';

        try {
            /** @var array<string, array<string, mixed>> $params */
            $params = require dirname(__DIR__) . '/config/params.php';
            $config = $params['rasuvaeff/yii3-clickhouse-toolkit'];

            Assert::same($config['host'], 'ch.from-dotenv');
            Assert::same($config['migrationsPath'], '/srv/app/migrations');
            // Untouched keys still resolve to their documented defaults.
            Assert::same($config['port'], 8123);
            Assert::same($config['database'], 'default');
            Assert::same($config['migrationsTable'], '_migrations');
            // Namespace resolution is opt-in: empty by default, so the path
            // stays the only source until an application sets it.
            Assert::same($config['migrationsNamespace'], '');
        } finally {
            unset($_ENV['CLICKHOUSE_HOST'], $_SERVER['CLICKHOUSE_MIGRATIONS_PATH']);
        }
    }

    /**
     * @param array<string, mixed> $chOverrides
     *
     * @return array<string, mixed>
     */
    private function definitions(array $chOverrides = []): array
    {
        $params = [
            'rasuvaeff/yii3-clickhouse-toolkit' => $chOverrides + [
                'host' => '127.0.0.1',
                'port' => 8123,
                'database' => 'default',
                'username' => 'default',
                'password' => '',
                'secure' => false,
                'migrationsPath' => '',
            ],
        ];

        return (static fn(array $params): array => require dirname(__DIR__) . '/config/di.php')($params);
    }
}
