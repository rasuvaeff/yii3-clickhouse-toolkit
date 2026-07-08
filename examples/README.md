# Examples

Run from the package root with the dev dependencies installed
(`make install`), using the `composer:2` Docker image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/config-preview.php
```

| Script | Shows | Needs server? |
|---|---|---|
| `config-preview.php` | How `ClickHouseConfigFactory` coerces `CLICKHOUSE_*` env strings into a `ClickHouseConfig` (int port, boolean `secure`, computed base URI) | No |

`config-preview.php` builds only value objects — it never opens a connection, so
it runs offline. In a real Yii3 app you do not call the factory yourself: the
bundled `config/di.php` resolves `ClickHouseConfig` and the ClickHouse client for
you from the merged params.
