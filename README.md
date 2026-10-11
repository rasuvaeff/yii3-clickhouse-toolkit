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
- [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit) `^1.10` (pulled in automatically)
- [`rasuvaeff/context`](https://github.com/rasuvaeff/context) `^0.1` (pulled in automatically)
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
| `Rasuvaeff\ClickHouseToolkit\ContextClickHouseClient` | context-aware decorator | checks cancellation/deadlines and maps remaining time to `max_execution_time` |
| `Rasuvaeff\Context\Context` | background context by default | replace this binding with the current request/job context |
| `SimPod\ClickHouseClient\Client\ClickHouseClient` | alias → `ContextClickHouseClient` | type-hint the interface |
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
| `migrationsPath` | `CLICKHOUSE_MIGRATIONS_PATH` | *unset — required for migrations, unless `migrationsNamespace` is set* |
| `migrationsNamespace` | — | *unset — PSR-4 namespace resolved to the migrations directory* |
| `migrationsTable` | `CLICKHOUSE_MIGRATIONS_TABLE` | `_migrations` |
| `migrationPlaceholders` | — | `[]` |

### Where the values are read from

Each variable is looked up in `getenv()` first, then in `$_ENV`, then in
`$_SERVER`; the first non-empty one wins, and the default applies only when
none has it.

The fallback matters for `.env`-based deployments. `vlucas/phpdotenv`'s
`Dotenv::createImmutable()` — the variant the library recommends — deliberately
does not call `putenv()`: it writes to `$_ENV` and `$_SERVER` only. Reading
through `getenv()` alone therefore sees nothing on a plain PHP-FPM or CLI
deployment that relies on the `.env` file (a `php yii some:command` cron entry
is the typical case), and the package would silently use its defaults —
`127.0.0.1:8123`, database `default`, empty password — instead of reporting a
configuration error. Under Docker Compose it happens to work, because
`env_file:` puts the values into the container's process environment.

An empty value counts as unset and falls through to the next source; `"0"` does
not, so `CLICKHOUSE_SECURE=0` reads as configured.

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

### Locating migrations by namespace

`migrationsNamespace` is the alternative to spelling the path out. It is
resolved through Composer's PSR-4 map (`vendor/composer/autoload_psr4.php`),
the way `yiisoft/db-migration` resolves its own migration namespaces:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        'migrationsNamespace' => 'App\\Infrastructure\\ClickHouse\\Migration',
    ],
];
```

The namespace is fictitious as far as PHP is concerned — `*.sql` files hold no
classes — but the directory lives inside the application's PSR-4 tree, so
Composer's map is the right thing to ask. The two ways it beats a path:

- it is a property of the code, identical on every environment, so it is not a
  per-stand `.env` line that no local run can validate;
- it does not move when the config file does, unlike `dirname(__DIR__, 2)`.

| Rule | Detail |
|---|---|
| Precedence | An explicit non-empty `migrationsPath` wins. Applications already configured that way are unaffected, and a migrations directory outside the PSR-4 tree stays reachable. |
| Neither set | Unchanged: a `RuntimeException` naming both ways out. |
| Unresolvable namespace | A `RuntimeException` naming the namespace **and every path checked** — the diagnosis the path variant cannot give. |
| Longest prefix wins | A nested prefix (`App\Billing\`) may map elsewhere than its parent; the longest match owns the namespace, and a shorter one is tried only if the longer maps to nothing on disk. |
| Renamed vendor directory | Composer's map is looked up as `vendor/composer/autoload_psr4.php` above this package. An application that renamed the vendor directory configures `migrationsPath` instead. |

Both consumers — `ClickHouseMigrationRunner` and `ClickHouseMigrationGenerator` —
build their path from the same DI closure, so the namespace is resolved in one
place and reaches both.

### Migration bookkeeping table

`migrationsTable` names the table the runner records applied migrations in.
Two situations need something other than `_migrations`:

- **Adopting this package where `_migrations` already exists** with a different
  schema — a home-grown `(name, applied_at)` table, say. The runner's
  `CREATE TABLE IF NOT EXISTS` finds it and does nothing, then the first read
  fails on the missing `checksum` column — *before* any migration file is read,
  so the repair cannot itself ship as a migration. Point the runner at a fresh
  name, let it re-apply the (idempotent) migrations, and drop the old table
  whenever convenient.
- **Two applications sharing one ClickHouse database** — give each its own.

```dotenv
CLICKHOUSE_MIGRATIONS_TABLE=app_schema_migrations
```

The name is interpolated into SQL rather than bound, so `clickhouse-toolkit`
validates it as a plain identifier and throws otherwise; a db-qualified
`analytics._migrations` is refused too. A non-string param falls back to the
default rather than reaching a string argument.

### Migration placeholders

`migrationPlaceholders` is passed to the runner as `{{key}}` substitutions,
applied to every migration file **before** it is hashed and executed. That is
how a package can ship DDL whose table name the application configures instead
of hard-coding it:

```php
// config/common/params.php
'rasuvaeff/yii3-clickhouse-toolkit' => [
    'migrationPlaceholders' => [
        'exposures_table' => 'ab_exposures',
    ],
],
```

Non-string keys and non-scalar values are dropped rather than reaching
`str_replace()`. An unresolved `{{…}}` makes the runner throw, naming the file
and the token — a typo does not travel to ClickHouse. Changing a value after a
migration has been applied is reported as a divergence; see the
`rasuvaeff/clickhouse-toolkit` README for what to do then.


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

The bridge wraps the live client in `ContextClickHouseClient`. It uses a
background context by default, preserving the behaviour of existing installs.
Keppio or another Yii3 application can bind its current request/job context:

```php
use Rasuvaeff\Context\Context;

return [
    Context::class => static fn (): Context => $currentContext,
];
```

The context is checked before and after every ClickHouse operation. When it has
a deadline, the remaining budget is sent to ClickHouse as `max_execution_time`.

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
