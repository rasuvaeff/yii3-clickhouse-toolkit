<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit;

/**
 * Resolves a PSR-4 namespace to the directory Composer maps it to.
 *
 * `rasuvaeff/clickhouse-toolkit` takes an absolute path to the migrations
 * directory, which every application then has to produce for itself: either
 * from an environment variable, different on every stand and undiagnosable when
 * mistyped, or computed from the config file's own location with `dirname()`,
 * which breaks the moment the config moves a level. The namespace is neither —
 * it is a property of the code, identical everywhere, and Composer already
 * knows where it lives.
 *
 * The namespace is fictitious as far as PHP is concerned: `*.sql` files hold no
 * classes. The directory is still inside the application's PSR-4 tree, so the
 * mapping is the right one to ask.
 *
 * @internal
 */
final readonly class MigrationsDirectoryResolver
{
    /**
     * @param string|null $vendorDir Composer's vendor directory. Located from this
     *                               file's position when null, which covers both an
     *                               installed application and the package checkout.
     */
    public function __construct(private ?string $vendorDir = null) {}

    /**
     * @throws \RuntimeException when the namespace is empty, no PSR-4 prefix covers
     *                           it, or nothing it maps to exists on disk
     */
    public function resolve(string $namespace): string
    {
        $namespace = trim($namespace, '\\');

        if ($namespace === '') {
            throw new \RuntimeException('ClickHouse migrations namespace is empty.');
        }

        $checked = [];

        foreach ($this->matchingPrefixes($namespace) as $prefix => $directories) {
            $suffix = str_replace('\\', '/', substr($namespace, $prefix === '' ? 0 : strlen($prefix) + 1));

            foreach ($directories as $directory) {
                $path = rtrim($directory, '/\\') . ($suffix === '' ? '' : '/' . $suffix);
                $checked[] = $path;

                if (is_dir($path)) {
                    return $path;
                }
            }
        }

        throw new \RuntimeException(sprintf(
            'ClickHouse migrations namespace "%s" does not resolve to an existing directory. %s',
            $namespace,
            $checked === []
                ? sprintf('No PSR-4 prefix in "%s" matches it.', $this->psr4MapFile())
                : 'Checked: ' . implode(', ', $checked) . '.',
        ));
    }

    /**
     * Prefixes covering the namespace, longest first: Composer lets a nested
     * prefix point somewhere else entirely, and that mapping is the one that owns
     * the namespace. Equal-length prefixes are ordered by name so the reported
     * candidate paths do not depend on the map's iteration order.
     *
     * @return array<string, list<string>> Trimmed prefix => mapped directories
     */
    private function matchingPrefixes(string $namespace): array
    {
        $matching = [];

        foreach ($this->psr4Map() as $prefix => $directories) {
            $prefix = trim($prefix, '\\');

            if ($prefix !== '' && $namespace !== $prefix && !str_starts_with($namespace, $prefix . '\\')) {
                continue;
            }

            $matching[$prefix] = $directories;
        }

        uksort(
            $matching,
            static fn(string $a, string $b): int => [strlen($b), $a] <=> [strlen($a), $b],
        );

        return $matching;
    }

    /**
     * @return array<string, list<string>>
     */
    private function psr4Map(): array
    {
        $file = $this->psr4MapFile();

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Composer PSR-4 map "%s" does not exist.', $file));
        }

        /** @var array<string, list<string>> $map */
        $map = require $file;

        return $map;
    }

    private function psr4MapFile(): string
    {
        if ($this->vendorDir !== null) {
            return rtrim($this->vendorDir, '/\\') . '/composer/autoload_psr4.php';
        }

        // Installed, this file sits at vendor/rasuvaeff/yii3-clickhouse-toolkit/src
        // and the map is in the application's vendor/; in the package's own
        // checkout it is one directory up. Walking up covers both without the
        // caller having to know which one it is. An application that renamed
        // Composer's vendor directory falls outside this and configures
        // "migrationsPath" instead.
        $directory = __DIR__;

        while (true) {
            $candidate = $directory . '/vendor/composer/autoload_psr4.php';

            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                throw new \RuntimeException(
                    'Composer PSR-4 map (vendor/composer/autoload_psr4.php) was not found above "' . __DIR__ . '". '
                    . 'Pass the vendor directory explicitly, or configure "migrationsPath" instead.',
                );
            }

            $directory = $parent;
        }
    }
}
