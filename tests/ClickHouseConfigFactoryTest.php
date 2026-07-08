<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit\Tests;

use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ClickHouseConfigFactory::class)]
final class ClickHouseConfigFactoryTest
{
    private ClickHouseConfigFactory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new ClickHouseConfigFactory();
    }

    public function buildsConfigFromFullParams(): void
    {
        $config = $this->factory->fromParams([
            'host' => 'ch.internal',
            'port' => 9000,
            'database' => 'analytics',
            'username' => 'writer',
            'password' => 's3cret',
            'secure' => true,
        ]);

        Assert::same($config->host, 'ch.internal');
        Assert::same($config->port, 9000);
        Assert::same($config->database, 'analytics');
        Assert::same($config->username, 'writer');
        Assert::same($config->password, 's3cret');
        Assert::true($config->secure);
    }

    public function appliesDefaultsForEmptyParams(): void
    {
        $config = $this->factory->fromParams([]);

        Assert::same($config->host, '127.0.0.1');
        Assert::same($config->port, 8123);
        Assert::same($config->database, 'default');
        Assert::same($config->username, 'default');
        Assert::same($config->password, '');
        Assert::false($config->secure);
    }

    public function coercesStringPortToInt(): void
    {
        $config = $this->factory->fromParams(['port' => '8443']);

        Assert::same($config->port, 8443);
    }

    #[DataProvider('secureProvider')]
    public function coercesSecureFlag(mixed $input, bool $expected): void
    {
        $config = $this->factory->fromParams(['secure' => $input]);

        Assert::same($config->secure, $expected);
    }

    public static function secureProvider(): iterable
    {
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'string true' => ['true', true];
        yield 'string 1' => ['1', true];
        yield 'string on' => ['on', true];
        yield 'string yes' => ['yes', true];
        yield 'uppercase TRUE' => ['TRUE', true];
        yield 'mixed-case On' => ['On', true];
        yield 'whitespace-padded true' => ['  true  ', true];
        yield 'whitespace-padded 1' => [" 1\n", true];
        yield 'string false' => ['false', false];
        yield 'string 0' => ['0', false];
        yield 'string off' => ['off', false];
        yield 'empty string' => ['', false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
    }
}
