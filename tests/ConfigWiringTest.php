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
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
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
        $client = $this->container()->get(\SimPod\ClickHouseClient\Client\ClickHouseClient::class);

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

            return;
        }

        Assert::fail('Expected a RuntimeException when migrationsPath is empty');
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
