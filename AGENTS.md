# AGENTS.md — yii3-clickhouse-toolkit

Guidance for AI agents working on this package. Read before changing code.

## What this is

A Yii3 **config bridge** for `rasuvaeff/clickhouse-toolkit` (namespace
`Rasuvaeff\Yii3ClickHouseToolkit`). It ships `config/di.php` +
`config/params.php` and a single factory class, `ClickHouseConfigFactory`.
Installing the package wires the toolkit's `ClickHouseConfig`,
`ClickHouseClientFactory`, `PsrClickHouseClient` (+ `ClickHouseClient` alias),
migration runner/generator and three migration console commands into a Yii3
container straight from `CLICKHOUSE_*` environment variables.

Public API: `ClickHouseConfigFactory`. Everything else the package exposes is
DI configuration, not code.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **This bridge is the single binder of the toolkit client/config.** `config/di.php`
   is the ONLY place `ClickHouseConfig`, `ClickHouseClientFactory`,
   `PsrClickHouseClient`/`ClickHouseClient` and the migration services get bound.
   Backend packages (e.g. `yii3-outbox-clickhouse`) consume the factory and must
   never bind it — two binders of one key is a `yiisoft/config` `Duplicate key`
   error. When you touch `config/`, re-run the merge harness (below) against
   `yii3-outbox-clickhouse` before claiming done.
4. **Preserve the public contract.** Update README + `llms.txt` + tests with any
   change to the wired ids, params keys or command names.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`,
`make test-coverage`, `make mutation` (minMsi 100), `make release-check`.
`make mutation` / `make test-coverage` bootstrap `pcov` inside the container.

## Invariants & gotchas

- **`config/` is not covered by the build gate.** cs (Finder = src/tests/examples),
  psalm (src-only) and testo (src coverage) never see `config/di.php` or
  `config/params.php`. They are exercised instead by `tests/ConfigWiringTest.php`
  through a real `Yiisoft\Di\Container`. A regression in the wiring will pass cs
  and psalm — only the wiring test catches it. Keep it green and extend it when
  you add a binding.
- **`migrationsPath` throws when empty, by design.** The runner/generator
  closures call a resolver that raises a `RuntimeException` naming
  `CLICKHOUSE_MIGRATIONS_PATH` instead of shipping an empty-string default that
  would operate relative to the working directory. Do not "fix" it with a default.
- **PSR-18 injection is explicit.** `ClickHouseClientFactory` is bound with a
  closure that reads `Psr\Http\Client\ClientInterface` and the PSR-17 factories
  from the container via `has()`/`get()`, honouring an app-configured client
  (timeouts/TLS) and falling back to php-http discovery. `ConfigWiringTest`
  asserts an app-bound client actually reaches the factory (reflection). Do not
  drop back to bare autowiring — it makes the injection contract untested.
- **`params` recursive merge is assumed.** Registering commands under
  `yiisoft/yii-console.commands` only co-exists with other packages because real
  Yii3 apps run `RecursiveMerge::groups('params', …)` (the app-template default).
  The merge harness must pass that modifier to reflect reality.
- **Merge harness (definition of done for `config/` changes).** Reproduce a real
  `yiisoft/config` merge with a fake vendor layout containing this bridge +
  `yii3-outbox-clickhouse`, path repos, real merge plan, `RecursiveMerge` on
  `params`, run in Docker `composer:2 php`. Assert `Config::get('di')` and
  `get('params')` build with no `Duplicate key`. Green wiring-test = "closures
  work"; green merge-harness = "composes with the real downstream consumer".
- Code: `declare(strict_types=1)`, `final readonly class`, `@api`, explicit types.
- `examples/` is part of the public contract: keep scripts runnable and
  server-independent, and update `examples/README.md` when usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment. Never revert to
  floating `@vN` tags. Workflows carry `permissions: { contents: read }` and
  `persist-credentials: false` on every `actions/checkout`. Verify with
  `zizmor --persona=auditor .github/` — no `unpinned-uses`, `excessive-permissions`
  or `artipacked`.

## When you finish

- Update `README.md` + `llms.txt` (and `examples/` if usage changed); update
  `CHANGELOG.md` when releasing.
- Re-run `composer build` and, for `config/` changes, the merge harness. Paste
  the output. For release candidates also run `make release-check`.
