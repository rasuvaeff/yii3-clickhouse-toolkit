<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit\Tests;

use Rasuvaeff\Yii3ClickHouseToolkit\EnvironmentReader;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(EnvironmentReader::class)]
final class EnvironmentReaderTest
{
    private const string KEY = 'RASUVAEFF_CH_TEST_VALUE';

    private EnvironmentReader $reader;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->reader = new EnvironmentReader();
        $this->clear();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->clear();
    }

    public function readsFromTheProcessEnvironment(): void
    {
        putenv(self::KEY . '=from-getenv');

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-getenv');
    }

    /**
     * The case the fix exists for: Dotenv::createImmutable() writes here and
     * deliberately never calls putenv().
     */
    public function readsFromEnvSuperglobalWhenTheProcessEnvironmentIsEmpty(): void
    {
        $_ENV[self::KEY] = 'from-dotenv';

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-dotenv');
    }

    public function readsFromServerSuperglobalAsTheLastSource(): void
    {
        $_SERVER[self::KEY] = 'from-server';

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-server');
    }

    public function fallsBackToTheDefaultWhenNoSourceHasIt(): void
    {
        Assert::same($this->reader->read(self::KEY, 'fallback'), 'fallback');
    }

    public function defaultsToAnEmptyStringWhenNoneIsGiven(): void
    {
        Assert::same($this->reader->read(self::KEY), '');
    }

    /**
     * Precedence is unchanged where getenv() already answers — that is what
     * makes this a fix rather than a behaviour change.
     */
    public function processEnvironmentWinsOverBothSuperglobals(): void
    {
        putenv(self::KEY . '=from-getenv');
        $_ENV[self::KEY] = 'from-dotenv';
        $_SERVER[self::KEY] = 'from-server';

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-getenv');
    }

    public function envSuperglobalWinsOverServer(): void
    {
        $_ENV[self::KEY] = 'from-dotenv';
        $_SERVER[self::KEY] = 'from-server';

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-dotenv');
    }

    /**
     * An empty value means "not set" and moves on — the `getenv($k) ?: $default`
     * idiom this replaces behaved the same way.
     */
    public function skipsEmptyValuesAndKeepsLooking(): void
    {
        putenv(self::KEY . '=');
        $_ENV[self::KEY] = '';
        $_SERVER[self::KEY] = 'from-server';

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'from-server');
    }

    /**
     * "0" is not empty. `getenv($k) ?: $default` dropped it, which made
     * CLICKHOUSE_SECURE=0 unreadable.
     */
    public function keepsZeroAsAValue(): void
    {
        $_ENV[self::KEY] = '0';

        Assert::same($this->reader->read(self::KEY, 'fallback'), '0');
    }

    #[DataProvider('nonStringSuperglobalValuesProvider')]
    public function ignoresNonStringSuperglobalValues(mixed $value): void
    {
        $_ENV[self::KEY] = $value;

        Assert::same($this->reader->read(self::KEY, 'fallback'), 'fallback');
    }

    public static function nonStringSuperglobalValuesProvider(): iterable
    {
        yield 'int' => [8123];
        yield 'bool' => [true];
        yield 'null' => [null];
        yield 'array' => [['nested']];
    }

    private function clear(): void
    {
        putenv(self::KEY);
        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
    }
}
