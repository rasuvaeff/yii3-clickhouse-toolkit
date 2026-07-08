<?php

declare(strict_types=1);

use Rasuvaeff\Yii3ClickHouseToolkit\ClickHouseConfigFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

// The params the config plugin assembles from CLICKHOUSE_* env vars (env values
// arrive as strings; the factory coerces them). This mirrors config/params.php.
$params = [
    'host' => 'clickhouse.internal',
    'port' => '8443',
    'database' => 'analytics',
    'username' => 'writer',
    'password' => 's3cret',
    'secure' => 'true',
    'migrationsPath' => '/app/resources/clickhouse-migrations',
];

$config = (new ClickHouseConfigFactory())->fromParams($params);

printf("host:    %s\n", $config->host);
printf("port:    %d\n", $config->port);
printf("secure:  %s\n", $config->secure ? 'yes' : 'no');
printf("baseUri: %s\n", $config->baseUri());
