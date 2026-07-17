# rasuvaeff/yii3-clickhouse-toolkit

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/downloads)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Build](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit)
[![License](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/license)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[Русская версия](README.ru.md)

Yii3 config bridge for [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit).
Install it and a ClickHouse client, migration runner and the three migration
console commands are wired into the container straight from `CLICKHOUSE_*`
environment variables — no hand-written `config/di.php` boilerplate.

This package ships **only configuration** (`config/di.php` + `config/params.php`)
and a small parameter factory. All the actual ClickHouse machinery lives in
`rasuvaeff/clickhouse-toolkit`; this is the glue that makes it a one-line install
in a Yii3 application.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference you can use.

## Requirements

- PHP 8.3–8.5
- [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit) `^1.2` (pulled in automatically; migration commands need ≥ 1.2.0)
- A Yii3 application using [`yiisoft/config`](https://github.com/yiisoft/config)
  with the standard `RecursiveMerge::groups('params', …)` setup (the app template default)
- A PSR-18 HTTP client + PSR-17 factories (e.g. `guzzlehttp/guzzle`)

## Installation

```bash
composer require rasuvaeff/yii3-clickhouse-toolkit
```

`yiisoft/config` discovers the bundled config plugin automatically.

## What it wires

Once installed, the following container entries resolve from the merged config:

| Container id | Resolves to | Notes |
|---|---|---|
| `Rasuvaeff\ClickHouseToolkit\ClickHouseConfig` | `ClickHouseConfig` | built from the params below |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory` | `ClickHouseClientFactory` | picks up an app-bound PSR-18 client / PSR-17 factories if present |
| `SimPod\ClickHouseClient\Client\PsrClickHouseClient` | live client | via `ClickHouseClientFactory::create()` |
| `SimPod\ClickHouseClient\Client\ClickHouseClient` | alias → `PsrClickHouseClient` | type-hint the interface |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner` | migration runner | needs `migrationsPath` (see below) |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface` | alias → runner | |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator` | migration generator | needs `migrationsPath` |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder` | mutation builder | `ALTER … UPDATE/DELETE`, over the live client |
| `Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager` | partition manager | drop/attach/freeze/move partitions, over the live client |

Three console commands are registered under `yiisoft/yii-console`:

| Command | Action |
|---|---|
| `clickhouse:migrations:generate <description>` | create the next `NNN_*.sql` file |
| `clickhouse:migrations:status` | show applied / pending / missing / diverged |
| `clickhouse:migrations:migrate` | apply pending migrations |

## Configuration

Defaults come from environment variables. Override any of them by redefining the
`rasuvaeff/yii3-clickhouse-toolkit` params key in your application config.

| Param | Env var | Default |
|---|---|---|
| `host` | `CLICKHOUSE_HOST` | `127.0.0.1` |
| `port` | `CLICKHOUSE_PORT` | `8123` |
| `database` | `CLICKHOUSE_DB` | `default` |
| `username` | `CLICKHOUSE_USER` | `default` |
| `password` | `CLICKHOUSE_PASSWORD` | `''` |
| `secure` | `CLICKHOUSE_SECURE` | `false` (accepts `1/true/on/yes`) |
| `migrationsPath` | `CLICKHOUSE_MIGRATIONS_PATH` | *unset — required for migrations* |

`migrationsPath` has **no safe default**: resolving the migration runner or
generator without it throws a clear `RuntimeException` rather than silently
operating relative to the working directory. Set the env var, or point the param
at your migrations directory:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        'migrationsPath' => dirname(__DIR__, 2) . '/resources/clickhouse-migrations',
    ],
];
```

## Usage

Type-hint the client (or the interface) anywhere in your app:

```php
use SimPod\ClickHouseClient\Client\ClickHouseClient;

final readonly class ReportService
{
    public function __construct(private ClickHouseClient $client) {}

    public function activeUsers(): int
    {
        return (int) $this->client->select('SELECT count() FROM events')->getRows()[0]['count()'];
    }
}
```

Run migrations from the Yii3 console:

```bash
./yii clickhouse:migrations:generate "create events table"
./yii clickhouse:migrations:migrate
./yii clickhouse:migrations:status
```

### Custom PSR-18 client (timeouts / TLS)

Bind your own configured PSR-18 client in the app; the bridge injects it into
`ClickHouseClientFactory` automatically (it reads `Psr\Http\Client\ClientInterface`
and the PSR-17 factories from the container when they are bound, otherwise falls
back to auto-discovery):

```php
// config/common/di.php
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => static fn (): Client => new Client(['timeout' => 5.0]),
];
```

### Composition with backend packages

This bridge is the **single** binder of the toolkit client/config. Backend
packages such as [`rasuvaeff/yii3-outbox-clickhouse`](https://github.com/rasuvaeff/yii3-outbox-clickhouse)
consume `ClickHouseClientFactory` but never bind it, so installing both is
conflict-free (verified against a real `yiisoft/config` merge). Their console
commands and params co-exist under the standard recursive `params` merge.

## Security

- Connection credentials travel through environment variables and
  `X-ClickHouse-*` headers, never in the URI. Keep `CLICKHOUSE_PASSWORD` in your
  secret store, not in committed config.
- All query safety (parameterized queries, identifier validation) is the
  responsibility of `rasuvaeff/clickhouse-toolkit` — see its README.

## Examples

Runnable, server-independent examples live in [`examples/`](examples/).

## Development

No PHP/Composer on the host — everything runs in Docker via the `composer:2` image.

```bash
make install
make build          # validate → normalize → require-checker → cs → psalm → test
make cs-fix
make mutation       # minMsi 100
make release-check
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
