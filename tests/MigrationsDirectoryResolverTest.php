<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3ClickHouseToolkit\Tests;

use Rasuvaeff\Yii3ClickHouseToolkit\MigrationsDirectoryResolver;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
#[Covers(MigrationsDirectoryResolver::class)]
final class MigrationsDirectoryResolverTest
{
    /** @var list<string> */
    private array $tempDirs = [];

    #[AfterTest]
    public function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeRecursively($dir);
        }
        $this->tempDirs = [];
    }

    public function resolvesANamespaceMatchingAPrefixExactly(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Assert::same((new MigrationsDirectoryResolver($vendor))->resolve('App'), $root . '/src');
    }

    public function resolvesASubNamespaceToTheMatchingSubdirectory(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src/Infrastructure/ClickHouse/Migration', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('App\\Infrastructure\\ClickHouse\\Migration'),
            $root . '/src/Infrastructure/ClickHouse/Migration',
        );
    }

    /**
     * Composer allows a nested prefix to point somewhere else entirely. The
     * longest match is the one that owns the namespace; matching the shorter
     * prefix first would resolve to a directory the application never mapped.
     *
     * Both map orders are exercised: the map's own order must not decide the
     * answer, in either direction.
     */
    #[DataProvider('prefixOrderProvider')]
    public function prefersTheLongestMatchingPrefix(bool $longestListedFirst): void
    {
        $root = $this->tempDir();
        // Both candidates exist, so only the ordering decides — the shorter
        // prefix would otherwise answer with a directory that also happens to
        // be there, which is exactly the silent mis-resolution to avoid.
        mkdir($root . '/src/Billing/Migration', recursive: true);
        mkdir($root . '/modules/billing/Migration', recursive: true);

        $vendor = $this->vendorDir($root, $longestListedFirst
            ? ['App\\Billing\\' => [$root . '/modules/billing'], 'App\\' => [$root . '/src']]
            : ['App\\' => [$root . '/src'], 'App\\Billing\\' => [$root . '/modules/billing']]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('App\\Billing\\Migration'),
            $root . '/modules/billing/Migration',
        );
    }

    public static function prefixOrderProvider(): iterable
    {
        yield 'shorter prefix listed first' => [false];
        yield 'longer prefix listed first' => [true];
    }

    /**
     * A PSR-4 prefix may map to several directories; only one of them holds the
     * namespace.
     */
    public function fallsThroughToTheSecondMappedDirectory(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/second/Migration', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/first', $root . '/second']]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('App\\Migration'),
            $root . '/second/Migration',
        );
    }

    public function resolvesUnderAnEmptyFallbackPrefix(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/lib/Legacy', recursive: true);
        $vendor = $this->vendorDir($root, ['' => [$root . '/lib']]);

        Assert::same((new MigrationsDirectoryResolver($vendor))->resolve('Legacy'), $root . '/lib/Legacy');
    }

    public function ignoresALeadingBackslashAndATrailingSlashInTheMappedDirectory(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src/Migration', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src/']]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('\\App\\Migration'),
            $root . '/src/Migration',
        );
    }

    /**
     * The paths actually checked are the deliverable: that is precisely what a
     * bare "path does not exist" message cannot tell the operator.
     */
    public function throwsNamingTheNamespaceAndEveryCheckedPath(): void
    {
        $root = $this->tempDir();
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/first', $root . '/second']]);

        Expect::exception(RuntimeException::class)->withMessage(sprintf(
            'ClickHouse migrations namespace "App\\Migration" does not resolve to an existing directory. '
            . 'Checked: %s, %s.',
            $root . '/first/Migration',
            $root . '/second/Migration',
        ));

        (new MigrationsDirectoryResolver($vendor))->resolve('App\\Migration');
    }

    public function throwsWhenNoPrefixMatchesTheNamespace(): void
    {
        $root = $this->tempDir();
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Expect::exception(RuntimeException::class)->withMessage(sprintf(
            'ClickHouse migrations namespace "Other\\Migration" does not resolve to an existing directory. '
            . 'No PSR-4 prefix in "%s" matches it.',
            $vendor . '/composer/autoload_psr4.php',
        ));

        (new MigrationsDirectoryResolver($vendor))->resolve('Other\\Migration');
    }

    public function throwsWhenTheNamespaceIsEmpty(): void
    {
        $root = $this->tempDir();
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Expect::exception(RuntimeException::class)->withMessageContaining('empty');

        (new MigrationsDirectoryResolver($vendor))->resolve('\\');
    }

    public function throwsWhenTheVendorDirectoryHasNoPsr4Map(): void
    {
        $root = $this->tempDir();

        Expect::exception(RuntimeException::class)->withMessageContaining($root . '/composer/autoload_psr4.php');

        (new MigrationsDirectoryResolver($root))->resolve('App\\Migration');
    }

    /**
     * Without an explicit vendor directory the resolver has to find Composer's
     * map on its own — from inside `vendor/` in an installed application, and
     * from the package root when the package is the checkout being developed.
     */
    public function findsComposersMapWithoutAnExplicitVendorDirectory(): void
    {
        $resolved = (new MigrationsDirectoryResolver())->resolve('Rasuvaeff\\Yii3ClickHouseToolkit\\Tests');

        Assert::same($resolved, dirname(__DIR__) . '/tests');
    }

    /**
     * The longest prefix owns the namespace, but only if it actually holds the
     * directory: Composer maps a namespace, not a guarantee that every
     * sub-namespace lives under the same root.
     */
    public function fallsBackToAShorterPrefixWhenTheLongestOneHasNoSuchDirectory(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src/Billing/Migration', recursive: true);
        mkdir($root . '/modules/billing', recursive: true);
        $vendor = $this->vendorDir($root, [
            'App\\Billing\\' => [$root . '/modules/billing'],
            'App\\' => [$root . '/src'],
        ]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('App\\Billing\\Migration'),
            $root . '/src/Billing/Migration',
        );
    }

    /**
     * The exact shape of the bug fixed upstream in `yiisoft/db-migration` 2.1.0
     * (our PR #350): Composer emits a core package's prefix before its `-db`
     * sibling, and the core namespace is a plain string prefix of it. Matching
     * on the raw string, then cutting with the untrimmed key length, resolved
     * `…AbTestingDb\Migration` to `<core>/src/b/Migration` — note the stray
     * `b/`. Discovery skipped the missing directory and `migrate:up` exited 0
     * having created nothing.
     *
     * The mangled directory exists here on purpose: upstream the fault was long
     * masked by an `is_dir()` fall-through, which resolves silently wrong the
     * moment that fragment path happens to be real.
     */
    public function doesNotResolveIntoASiblingPackageTheWayDbMigrationDid(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/core/src/b/Migration', recursive: true);
        mkdir($root . '/db/src/Migration', recursive: true);
        $vendor = $this->vendorDir($root, [
            'Rasuvaeff\\Yii3AbTesting\\' => [$root . '/core/src'],
            'Rasuvaeff\\Yii3AbTestingDb\\' => [$root . '/db/src'],
        ]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor))->resolve('Rasuvaeff\\Yii3AbTestingDb\\Migration'),
            $root . '/db/src/Migration',
        );
    }

    /**
     * `AppOther` starts with the characters of the `App\` prefix without being
     * inside it. Matching on the raw string would resolve it under a mapping the
     * application never made.
     */
    public function doesNotTreatAStringPrefixAsANamespacePrefix(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src/Migration', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Expect::exception(RuntimeException::class)->withMessage(sprintf(
            'ClickHouse migrations namespace "AppOther\\Migration" does not resolve to an existing directory. '
            . 'No PSR-4 prefix in "%s" matches it.',
            $vendor . '/composer/autoload_psr4.php',
        ));

        (new MigrationsDirectoryResolver($vendor))->resolve('AppOther\\Migration');
    }

    public function acceptsAVendorDirectoryWithATrailingSeparator(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/src/Migration', recursive: true);
        $vendor = $this->vendorDir($root, ['App\\' => [$root . '/src']]);

        Assert::same(
            (new MigrationsDirectoryResolver($vendor . '\\'))->resolve('App\\Migration'),
            $root . '/src/Migration',
        );
    }

    /**
     * @param array<string, list<string>> $map
     */
    private function vendorDir(string $root, array $map): string
    {
        $vendor = $root . '/vendor';
        mkdir($vendor . '/composer', recursive: true);
        file_put_contents(
            $vendor . '/composer/autoload_psr4.php',
            "<?php\n\nreturn " . var_export($map, true) . ";\n",
        );

        return $vendor;
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/chns_' . uniqid('', more_entropy: true);
        mkdir($dir);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function removeRecursively(string $path): void
    {
        if (is_dir($path)) {
            $entries = scandir($path);
            foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
                $this->removeRecursively($path . '/' . $entry);
            }
            rmdir($path);

            return;
        }

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
