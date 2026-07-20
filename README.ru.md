# rasuvaeff/yii3-clickhouse-toolkit

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/downloads)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Build](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit)
[![License](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/license)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[English version](README.md)

Yii3 config-bridge для [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit).
Установите его — и ClickHouse-клиент, migration-runner и три консольные команды
миграций подключаются к контейнеру прямо из переменных окружения `CLICKHOUSE_*`
— без ручного шаблонного `config/di.php`.

Пакет поставляет **только конфигурацию** (`config/di.php` + `config/params.php`)
и небольшую parameter-фабрику. Вся реальная ClickHouse-механика находится в
`rasuvaeff/clickhouse-toolkit`; это связка, делающая установку однострочной в
Yii3-приложении.

> Используете AI-ассистента для написания кода? В [llms.txt](llms.txt) — компактный
> API-справочник.

## Требования

- PHP 8.3–8.5
- [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit) `^1.2` (pull'ится автоматически; миграционные команды требуют ≥ 1.2.0)
- Yii3-приложение, использующее [`yiisoft/config`](https://github.com/yiisoft/config)
  со стандартной настройкой `RecursiveMerge::groups('params', …)` (дефолт app-template'а)
- PSR-18 HTTP-клиент + PSR-17 фабрики (например `guzzlehttp/guzzle`)

## Установка

```bash
composer require rasuvaeff/yii3-clickhouse-toolkit
```

`yiisoft/config` автоматически обнаруживает bundled config-plugin.

## Что подключается

После установки следующие container-entries разрешаются из смерженного конфига:

| Container id | Разрешается в | Заметки |
|---|---|---|
| `Rasuvaeff\ClickHouseToolkit\ClickHouseConfig` | `ClickHouseConfig` | строится из params ниже |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory` | `ClickHouseClientFactory` | подхватывает app-bound PSR-18 клиент / PSR-17 фабрики, если они есть |
| `SimPod\ClickHouseClient\Client\PsrClickHouseClient` | live client | через `ClickHouseClientFactory::create()` |
| `SimPod\ClickHouseClient\Client\ClickHouseClient` | alias → `PsrClickHouseClient` | type-hint интерфейса |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner` | migration runner | требует `migrationsPath` (см. ниже) |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface` | alias → runner | |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator` | migration generator | требует `migrationsPath` |
| `Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder` | mutation builder | `ALTER … UPDATE/DELETE`, поверх live-клиента |
| `Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager` | partition manager | drop/attach/freeze/move partition'ов, поверх live-клиента |

Три консольные команды регистрируются в `yiisoft/yii-console`:

| Команда | Действие |
|---|---|
| `clickhouse:migrations:generate <description>` | создать следующий `NNN_*.sql` файл |
| `clickhouse:migrations:status` | показать applied / pending / missing / diverged |
| `clickhouse:migrations:migrate` | применить pending-миграции |

## Конфигурация

Дефолты берутся из переменных окружения. Переопределите любой из них,
переопределив params-ключ `rasuvaeff/yii3-clickhouse-toolkit` в конфиге приложения.

| Param | Env var | По умолчанию |
|---|---|---|
| `host` | `CLICKHOUSE_HOST` | `127.0.0.1` |
| `port` | `CLICKHOUSE_PORT` | `8123` |
| `database` | `CLICKHOUSE_DB` | `default` |
| `username` | `CLICKHOUSE_USER` | `default` |
| `password` | `CLICKHOUSE_PASSWORD` | `''` |
| `secure` | `CLICKHOUSE_SECURE` | `false` (принимает `1/true/on/yes`) |
| `migrationsPath` | `CLICKHOUSE_MIGRATIONS_PATH` | *unset — требуется для миграций* |

У `migrationsPath` **нет безопасного дефолта**: резолв migration runner'а или
generator'а без него бросает явное `RuntimeException`, а не молча работает
относительно рабочего каталога. Установите env-переменную или укажите param на
ваш каталог миграций:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        'migrationsPath' => dirname(__DIR__, 2) . '/resources/clickhouse-migrations',
    ],
];
```

## Использование

Type-hint'ьте клиент (или интерфейс) в любом месте приложения:

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

Запустите миграции из консоли Yii3:

```bash
./yii clickhouse:migrations:generate "create events table"
./yii clickhouse:migrations:migrate
./yii clickhouse:migrations:status
```

### Кастомный PSR-18 клиент (timeouts / TLS)

Забиндите собственный сконфигурированный PSR-18 клиент в приложении; bridge
автоматически инжектирует его в `ClickHouseClientFactory` (он читает
`Psr\Http\Client\ClientInterface` и PSR-17 фабрики из контейнера, когда они
привязаны, иначе fallback'ает на auto-discovery):

```php
// config/common/di.php
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => static fn (): Client => new Client(['timeout' => 5.0]),
];
```

### Композиция с backend-пакетами

Этот bridge — **единственный** биндер toolkit client/config. Backend-пакеты
наподобие [`rasuvaeff/yii3-outbox-clickhouse`](https://github.com/rasuvaeff/yii3-outbox-clickhouse)
потребляют `ClickHouseClientFactory`, но никогда не биндят его, поэтому установка
обоих проходит без конфликтов (проверено против реального `yiisoft/config`
merge). Их консольные команды и params сосуществуют под стандартным рекурсивным
`params` merge.

## Безопасность

- Учётные данные подключения передаются через переменные окружения и
  `X-ClickHouse-*` headers, никогда в URI. Храните `CLICKHOUSE_PASSWORD` в вашем
  secret-store, а не в закоммиченном конфиге.
- Вся безопасность запросов (parameterized queries, identifier validation) —
  ответственность `rasuvaeff/clickhouse-toolkit` — см. его README.

## Примеры

Запускаемые, server-independent примеры лежат в [`examples/`](examples/).

## Разработка

На хосте нет PHP/Composer — всё запускается в Docker через образ `composer:2`.

```bash
make install
make build          # validate → normalize → require-checker → cs → psalm → test
make cs-fix
make mutation       # minMsi 100
make release-check
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
