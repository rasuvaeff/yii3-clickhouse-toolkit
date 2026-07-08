<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit\Benchmarks;

use Rasuvaeff\ClickHouseToolkit\ClickHouseConfig;
use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;
use Testo\Bench;

final class ClickHouseConfigFactoryBench
{
    #[Bench(
        calls: 10_000,
        iterations: 10,
    )]
    public static function fromEnvironmentStrings(): ClickHouseConfig
    {
        return (new ClickHouseConfigFactory())->fromParams([
            'host' => 'clickhouse.internal',
            'port' => '8443',
            'database' => 'analytics',
            'username' => 'writer',
            'password' => 's3cret',
            'secure' => 'true',
        ]);
    }
}
