# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Require `rasuvaeff/clickhouse-toolkit ^1.10` and `rasuvaeff/context ^0.1`.
- Wire `ContextClickHouseClient` into the Yii3 client alias, with a replaceable
  background `Context` binding for request/job deadlines and cancellation.

## 1.3.0 — 2026-09-11

- New `migrationsNamespace` param: the migrations directory can be given as a
  PSR-4 namespace, resolved through `vendor/composer/autoload_psr4.php` the way
  `yiisoft/db-migration` resolves its own. A path had to come either from an
  environment variable — different on every stand, undiagnosable when mistyped —
  or from `dirname()` arithmetic against the config file's own location, which
  breaks when the config moves; a namespace is a property of the code and is
  identical everywhere. An explicit `migrationsPath` still wins, so existing
  configurations are unaffected, and a namespace that resolves to nothing throws
  naming the namespace and every path checked. Params-only by design: reading it
  from the environment would put the directory back into per-stand configuration
  (#11).

## 1.2.0 — 2026-09-10

- New `migrationsTable` param (`CLICKHOUSE_MIGRATIONS_TABLE`, default
  `_migrations`), passed to `ClickHouseMigrationRunner`. Without it the runner's
  new `$migrationsTable` argument was unreachable for applications wiring
  through this bridge — and with it, adopting the package on top of an existing
  `_migrations` of a different schema no longer requires dropping or altering
  that table by hand on every environment. Requires
  `rasuvaeff/clickhouse-toolkit: ^1.7` (#8).

## 1.1.1 — 2026-09-10

- Configuration is read from `getenv()`, then `$_ENV`, then `$_SERVER`, instead
  of `getenv()` alone. A `.env` loaded with `Dotenv::createImmutable()` never
  reaches `getenv()` — the library writes to the superglobals and deliberately
  skips `putenv()` — so on a plain PHP-FPM or CLI deployment every setting
  silently fell back to its default (`127.0.0.1:8123`, database `default`, empty
  password) with nothing reporting that the configuration had been skipped.
  Precedence is unchanged wherever `getenv()` already answered (#7).

## 1.1.0 — 2026-07-25

- New `migrationPlaceholders` param, passed to `ClickHouseMigrationRunner` as
  `{{key}}` substitutions (requires `rasuvaeff/clickhouse-toolkit` `^1.6`). It
  is what lets a package ship `*.sql` DDL whose table names the application
  configures — without it, a params key that repoints a runtime writer leaves
  the shipped migration creating a different table.
- Malformed entries (non-string keys, non-scalar values) are dropped in
  `config/di.php` rather than reaching `str_replace()`: params come from an
  application's config file, not from this package.

## 1.0.0 — 2026-07-08

- Initial release: Yii3 config bridge for `rasuvaeff/clickhouse-toolkit`.
- Wires `ClickHouseConfig`, `ClickHouseClientFactory`, `PsrClickHouseClient`
  (+ `ClickHouseClient` alias), the migration runner/generator and three
  `clickhouse:migrations:*` console commands straight from `CLICKHOUSE_*`
  environment variables.
- Wires `ClickHouseMutationBuilder` and `ClickHousePartitionManager` as
  client-only singletons.
- `ClickHouseConfigFactory` coerces environment strings to a typed
  `ClickHouseConfig` (`port` → int, `secure` → textual bool).
