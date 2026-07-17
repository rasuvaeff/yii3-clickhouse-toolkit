# rasuvaeff/yii3-clickhouse-toolkit
[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/downloads)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
[![Build](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-clickhouse-toolkit/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-clickhouse-toolkit)
[![License](https://poser.pugx.org/rasuvaeff/yii3-clickhouse-toolkit/license)](https://packagist.org/packages/rasuvaeff/yii3-clickhouse-toolkit)
Yii3 config bridge for [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit).
Установите его, и клиент ClickHouse, средство миграции и три консольные команды миграции
 будут подключены к контейнеру прямо из переменных среды `CLICKHOUSE_*`
 — без рукописного шаблона `config/di.php`.

 Этот пакет включает **только конфигурацию** (`config/di.php` + `config/params.php`)
 и небольшую фабрику параметров. Все оборудование ClickHouse находится в
 `rasuvaeff/clickhouse-toolkit`; это связующее звено, которое делает установку
 в одну строку в приложении Yii3.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которую вы можете использовать. @@ЛИНИЯ@@
## Требования
- PHP 8,3–8,5
- [`rasuvaeff/clickhouse-toolkit`](https://github.com/rasuvaeff/clickhouse-toolkit) `^1.2` (pulled in automatically; migration commands need ≥ 1.2.0)
- A Yii3 application using [`yiisoft/config`](https://github.com/yiisoft/config)
со стандартной настройкой `RecursiveMerge::groups('params', …)` (шаблон приложения по умолчанию)
 - HTTP-клиент PSR-18 + фабрики PSR-17 (например, `guzzlehttp/guzzle`)

## Установка
```bash
composer require rasuvaeff/yii3-clickhouse-toolkit
```
`yiisoft/config` автоматически обнаруживает прилагаемый плагин конфигурации. @@ЛИНИЯ@@
## Что он соединяет
После установки следующие записи контейнера разрешаются из объединенной конфигурации:

 | Идентификатор контейнера | Разрешается | Заметки |
 |---|---|---|
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseConfig` | `ClickHouseConfig` | построено на основе параметров ниже |
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseClientFactory` | `ClickHouseClientFactory` | подхватывает привязанный к приложению клиент PSR-18 / фабрики PSR-17, если они есть |
 | `SimPod\ClickHouseClient\Client\PsrClickHouseClient` | живой клиент | через `ClickHouseClientFactory::create()` |
 | `SimPod\ClickHouseClient\Client\ClickHouseClient` | псевдоним → `PsrClickHouseClient` | подсказка типа интерфейса |
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunner` | бегун по миграции | нужен `migrationsPath` (см. ниже) |
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationRunnerInterface` | псевдоним → бегун | |
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseMigrationGenerator` | генератор миграции | нужен `migrationsPath` |
 | `Rasuvaeff\ClickHouseToolkit\ClickHouseMutationBuilder` | построитель мутаций | `ALTER… UPDATE/DELETE`, в работающем клиенте |
 | `Rasuvaeff\ClickHouseToolkit\ClickHousePartitionManager` | менеджер разделов | удалить/присоединить/заморозить/переместить разделы через работающий клиент |

 В `yiisoft/yii-console` зарегистрированы три консольные команды:

 | Команда | Действие |
 |---|---|
 | `clickhouse:migrations:generate <description>` | создать следующий файл `NNN_*.sql` |
 | `clickhouse:migrations:status` | показать примененное/ожидающее/отсутствующее/расходящееся |
 | `clickhouse:migrations:migrate` | применить ожидающие миграции | @@ЛИНИЯ@@
## Конфигурация
Значения по умолчанию берутся из переменных среды. Переопределите любой из них, переопределив ключ параметров
 `rasuvaeff/yii3-clickhouse-toolkit` в конфигурации вашего приложения.

 | Парам | Конверт вар | По умолчанию |
 |---|---|---|
 | `хозяин` | `CLICKHOUSE_HOST` | `127.0.0.1` |
 | `порт` | `CLICKHOUSE_PORT` | `8123` |
 | `база данных` | `CLICKHOUSE_DB` | `по умолчанию` |
 | `имя пользователя` | `CLICKHOUSE_USER` | `по умолчанию` |
 | `пароль` | `CLICKHOUSE_PASSWORD` | `''` |
 | `безопасный` | `CLICKHOUSE_SECURE` | `false` (принимает `1/true/on/yes`) |
 | `миграцииПуть` | `CLICKHOUSE_MIGRATIONS_PATH` | *unset — требуется для миграции* |

 `migrationsPath` не имеет **безопасного значения по умолчанию**: разрешение средства миграции или генератора
 без него выдает явное `RuntimeException`, а не молча
 работает относительно рабочего каталога. Задайте переменную env или укажите параметр
 в каталоге миграций:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-clickhouse-toolkit' => [
        'migrationsPath' => dirname(__DIR__, 2) . '/resources/clickhouse-migrations',
    ],
];
```
## Использование
Введите подсказку о клиенте (или интерфейсе) в любом месте вашего приложения:

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
Запустите миграцию из консоли Yii3:

```bash
./yii clickhouse:migrations:generate "create events table"
./yii clickhouse:migrations:migrate
./yii clickhouse:migrations:status
```
### Пользовательский клиент PSR-18 (таймауты/TLS)
Привяжите в приложении собственный настроенный клиент PSR-18; мост автоматически вводит его в
 `ClickHouseClientFactory` (он читает `Psr\Http\Client\ClientInterface`
 и фабрики PSR-17 из контейнера, когда они привязаны, в противном случае
 возвращается к автообнаружению):

```php
// config/common/di.php
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => static fn (): Client => new Client(['timeout' => 5.0]),
];
```
### Композиция с бэкэнд-пакетами
Этот мост является **единственной** связкой клиента/конфигурации набора инструментов. Бэкэнд
packages such as [`rasuvaeff/yii3-outbox-clickhouse`](https://github.com/rasuvaeff/yii3-outbox-clickhouse)
потребляйте `ClickHouseClientFactory`, но никогда не связывайте его, поэтому установка обоих является
 бесконфликтной (проверено на соответствие реальному слиянию `yiisoft/config`). Их консольные команды
 и параметры сосуществуют в рамках стандартного рекурсивного слияния `params`. @@ЛИНИЯ@@
## Безопасность
— Учетные данные подключения передаются через переменные среды и заголовки
 `X-ClickHouse-*`, а не через URI. Храните `CLICKHOUSE_PASSWORD` в своем секретном хранилище
, а не в зафиксированной конфигурации.
 - Вся безопасность запросов (параметризованные запросы, проверка идентификатора) находится в ответственности
 `rasuvaeff/clickhouse-toolkit` — см. его README. @@ЛИНИЯ@@
## Примеры
Выполняемые, независимые от сервера примеры находятся в [`examples/`](examples/). @@ЛИНИЯ@@
## Разработка
На хосте нет PHP/Composer — все работает в Docker через образ `composer:2`. @@ЛИНИЯ@@
```bash
make install
make build          # validate → normalize → require-checker → cs → psalm → test
make cs-fix
make mutation       # minMsi 100
make release-check
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
