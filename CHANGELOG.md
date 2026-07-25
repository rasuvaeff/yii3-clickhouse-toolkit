# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
