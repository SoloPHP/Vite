<?php

declare(strict_types=1);

namespace Solo\Vite\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solo\Vite\Exception\ViteException;
use Solo\Vite\Vite;

/**
 * The fixture is the manifest Vite 8.2 wrote for a sample app, which it split into these chunks:
 *
 * - resources/js/app.js: app.css, and imports of the shared and vendor chunks
 * - resources/js/admin.js: admin.css, and an import of the shared chunk
 * - resources/js/widget.js: an import of the vendor chunk
 * - resources/js/Pages/Home.js, which app.js imports dynamically: Home.css, and imports of the
 *   shared chunk and of the app entry
 * - the shared chunk: shared.css, and an import of the vendor chunk
 * - the vendor chunk: vendor.css
 * - resources/css/app.css, an entry of its own
 */
final class ViteTest extends TestCase
{
    private const string MANIFEST = __DIR__ . '/Fixtures/public/build/.vite/manifest.json';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/solo-vite-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testEntryLoadsWithItsCssAndTheCssAndPreloadsOfTheChunksItImports(): void
    {
        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<script type="module" src="/build/assets/app-B0eXWbkh.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
        ), $this->build()->tags('resources/js/app.js'));
    }

    public function testChunksAnEntryImportsThroughAnotherChunkLoadToo(): void
    {
        // admin.js imports the shared chunk only, and the shared chunk imports the vendor chunk
        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/admin-XYt9NWzn.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<script type="module" src="/build/assets/admin-BL8Vsp2e.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
        ), $this->build()->tags('resources/js/admin.js'));
    }

    public function testEntriesOfOnePageLoadTheChunksTheyShareOnce(): void
    {
        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<link rel="stylesheet" href="/build/assets/admin-XYt9NWzn.css">',
            '<script type="module" src="/build/assets/app-B0eXWbkh.js"></script>',
            '<script type="module" src="/build/assets/admin-BL8Vsp2e.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
        ), $this->build()->tags('resources/js/app.js', 'resources/js/admin.js'));
    }

    public function testCssEntryIsAStylesheet(): void
    {
        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/app-Bb7ih_3w.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<script type="module" src="/build/assets/widget-DNEbAnU5.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
        ), $this->build()->tags('resources/css/app.css', 'resources/js/widget.js'));
    }

    public function testPageComponentLoadsAfterTheAppThatImportsIt(): void
    {
        // As an Inertia app loads a first visit: the app, and the page it shows
        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<link rel="stylesheet" href="/build/assets/Home-DDLRubQx.css">',
            '<script type="module" src="/build/assets/app-B0eXWbkh.js"></script>',
            '<script type="module" src="/build/assets/Home-Bp2Q6BUW.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
        ), $this->build()->tags('resources/js/app.js', 'resources/js/Pages/Home.js'));
    }

    public function testEntryAnotherEntryImportsIsPreloadedUnlessItLoadsItself(): void
    {
        $build = $this->build();

        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/Home-DDLRubQx.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            '<script type="module" src="/build/assets/Home-Bp2Q6BUW.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
            '<link rel="modulepreload" href="/build/assets/app-B0eXWbkh.js">',
        ), $build->tags('resources/js/Pages/Home.js'));

        self::assertSame(self::tags(
            '<link rel="stylesheet" href="/build/assets/Home-DDLRubQx.css">',
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">',
            '<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">',
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            '<script type="module" src="/build/assets/Home-Bp2Q6BUW.js"></script>',
            '<script type="module" src="/build/assets/app-B0eXWbkh.js"></script>',
            '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            '<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">',
        ), $build->tags('resources/js/Pages/Home.js', 'resources/js/app.js'));
    }

    public function testEntryAskedForTwiceLoadsOnce(): void
    {
        $build = $this->build();
        self::assertSame(
            $build->tags('resources/js/app.js'),
            $build->tags('resources/js/app.js', 'resources/js/app.js'),
        );

        $devServer = $this->devServer('http://localhost:5173');
        self::assertSame(
            $devServer->tags('resources/js/app.js'),
            $devServer->tags('resources/js/app.js', 'resources/js/app.js'),
        );
    }

    public function testImportCycleEndsAtTheChunksAlreadyListed(): void
    {
        $manifest = $this->manifest([
            'resources/js/app.js' => ['file' => 'assets/app.js', 'imports' => ['_a.js']],
            '_a.js' => ['file' => 'assets/a.js', 'imports' => ['_b.js']],
            '_b.js' => ['file' => 'assets/b.js', 'imports' => ['_a.js', 'resources/js/app.js']],
        ]);

        self::assertSame(self::tags(
            '<script type="module" src="/build/assets/app.js"></script>',
            '<link rel="modulepreload" href="/build/assets/b.js">',
            '<link rel="modulepreload" href="/build/assets/a.js">',
        ), (new Vite($manifest, '/build/'))->tags('resources/js/app.js'));
    }

    public function testNoEntriesNoTags(): void
    {
        self::assertSame('', $this->build()->tags());
        self::assertSame('', $this->devServer('http://localhost:5173')->tags());
    }

    /** @return iterable<string, array{string, string}> */
    public static function baseUrls(): iterable
    {
        yield 'a path' => ['/build/', '/build/assets/widget-DNEbAnU5.js'];
        yield 'a path without the trailing slash' => ['/build', '/build/assets/widget-DNEbAnU5.js'];
        yield 'the root' => ['/', '/assets/widget-DNEbAnU5.js'];
        yield 'an empty relative base' => ['', 'assets/widget-DNEbAnU5.js'];
        yield 'an explicit relative base' => ['./', './assets/widget-DNEbAnU5.js'];
        yield 'another host' => [
            'https://cdn.example.com/build/',
            'https://cdn.example.com/build/assets/widget-DNEbAnU5.js',
        ];
    }

    #[DataProvider('baseUrls')]
    public function testBuildLoadsFromTheBaseUrl(string $baseUrl, string $script): void
    {
        $tags = (new Vite(self::MANIFEST, $baseUrl))->tags('resources/js/widget.js');

        self::assertStringContainsString('<script type="module" src="' . $script . '"></script>', $tags);
    }

    public function testFileNamesAreEncodedAsTheBrowserEncodesThem(): void
    {
        $manifest = $this->manifest([
            'resources/js/my page.js' => ['file' => 'assets/my page-ä1.js', 'imports' => ['_app(1)@x!.js']],
            '_app(1)@x!.js' => ['file' => "assets/app(1)@x!'-#?.js"],
        ]);

        // A chunk that imports ./app(1)@x!.js resolves it to the same URL, so it runs once
        self::assertSame(self::tags(
            '<script type="module" src="/build/assets/my%20page-%C3%A41.js"></script>',
            '<link rel="modulepreload" href="/build/assets/app(1)@x!&apos;-%23%3F.js">',
        ), (new Vite($manifest, '/build/'))->tags('resources/js/my page.js'));
    }

    public function testTwoNamesOfOneFileLoadItOnce(): void
    {
        $manifest = $this->manifest([
            'resources/js/app.js' => ['file' => 'assets/app.js', 'imports' => ['_shared.js']],
            'resources/js/admin.js' => ['file' => 'assets/admin.js', 'imports' => ['_shared-copy.js']],
            '_shared.js' => ['file' => 'assets/shared.js'],
            '_shared-copy.js' => ['file' => 'assets/shared.js'],
            'resources/js/shared.js' => ['file' => 'assets/shared.js'],
        ]);

        self::assertSame(self::tags(
            '<script type="module" src="/build/assets/app.js"></script>',
            '<script type="module" src="/build/assets/admin.js"></script>',
            '<link rel="modulepreload" href="/build/assets/shared.js">',
        ), (new Vite($manifest, '/build/'))->tags('resources/js/app.js', 'resources/js/admin.js'));

        self::assertSame(self::tags(
            '<script type="module" src="/build/assets/app.js"></script>',
            '<script type="module" src="/build/assets/shared.js"></script>',
        ), (new Vite($manifest, '/build/'))->tags('resources/js/app.js', 'resources/js/shared.js', '_shared-copy.js'));
    }

    public function testAssetIsNoEntry(): void
    {
        self::assertSame(
            'Vite entry resources/images/logo.svg is not a script or a stylesheet: '
            . 'the build makes it assets/logo-B_ApmSmS.svg',
            self::failure(fn() => $this->build()->tags('resources/images/logo.svg')),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function entriesNotFromTheRoot(): iterable
    {
        yield 'an absolute path' => ['/resources/js/app.js'];
        yield 'a path from the current directory' => ['./resources/js/app.js'];
        yield 'an empty name' => [''];
    }

    #[DataProvider('entriesNotFromTheRoot')]
    public function testEntryNotNamedFromTheViteRootFailsInBothModes(string $entry): void
    {
        $message = "Vite entry \"$entry\" is not a path from the Vite root, such as resources/js/app.js";

        self::assertSame($message, self::failure(fn() => $this->build()->tags($entry)));
        self::assertSame($message, self::failure(fn() => $this->devServer('http://localhost:5173')->tags($entry)));
    }

    /** @return iterable<string, array{string}> */
    public static function baseUrlsWithAQueryOrFragment(): iterable
    {
        yield 'a query' => ['/build/?v=3'];
        yield 'a fragment' => ['/build/#assets'];
    }

    #[DataProvider('baseUrlsWithAQueryOrFragment')]
    public function testBaseUrlWithAQueryOrFragmentIsRefused(string $baseUrl): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The Vite base URL $baseUrl must not have a query or a fragment");

        new Vite(self::MANIFEST, $baseUrl);
    }

    public function testUrlsAreEscapedForHtml(): void
    {
        $tags = (new Vite(self::MANIFEST, '/a&b"c\'d/'))->tags('resources/js/widget.js');

        self::assertStringContainsString('src="/a&amp;b&quot;c&apos;d/assets/widget-DNEbAnU5.js"', $tags);
    }

    public function testEntryTheBuildDoesNotHaveIsAnError(): void
    {
        self::assertSame(
            'Vite entry resources/js/missing.js is not in the manifest ' . self::MANIFEST,
            self::failure(fn() => $this->build()->tags('resources/js/app.js', 'resources/js/missing.js')),
        );
    }

    public function testMissingBuildIsAnError(): void
    {
        $manifest = $this->dir . '/manifest.json';

        self::assertSame(
            "Vite manifest $manifest not found: build the assets",
            self::failure(fn() => (new Vite($manifest, '/build/'))->tags('resources/js/app.js')),
        );
        self::assertSame(
            "Vite manifest $manifest not found: build the assets, or start the dev server",
            self::failure(fn() => (new Vite($manifest, '/build/', $this->dir . '/hot'))->tags('resources/js/app.js')),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidManifests(): iterable
    {
        yield 'not JSON' => ['{"resources/js/app.js": ', 'Syntax error'];
        yield 'a string instead of an object' => ['"resources/js/app.js"', 'it is not an object'];
        yield 'a list instead of an object' => ['[{"file": "assets/app.js"}]', 'it is not an object'];
        yield 'a chunk that is a string' => [
            '{"resources/js/app.js": "assets/app.js"}',
            'chunk resources/js/app.js is not an object',
        ];
        yield 'a chunk that is a list' => [
            '{"resources/js/app.js": []}',
            'chunk resources/js/app.js is not an object',
        ];
        yield 'a chunk without a file' => [
            '{"resources/js/app.js": {"src": "resources/js/app.js"}}',
            'chunk resources/js/app.js has no file',
        ];
        yield 'a chunk with an empty file' => [
            '{"resources/js/app.js": {"file": ""}}',
            'chunk resources/js/app.js has no file',
        ];
        yield 'css that is a string' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "css": "assets/app.css"}}',
            'css of chunk resources/js/app.js is not a list',
        ];
        yield 'css that is an object' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "css": {}}}',
            'css of chunk resources/js/app.js is not a list',
        ];
        yield 'css that is null' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "css": null}}',
            'css of chunk resources/js/app.js is not a list',
        ];
        yield 'css that are not names' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "css": [null]}}',
            'css of chunk resources/js/app.js is not a list of names',
        ];
        yield 'css with an empty name' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "css": [""]}}',
            'css of chunk resources/js/app.js is not a list of names',
        ];
        yield 'imports that are an object' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "imports": {"shared": "_shared.js"}}}',
            'imports of chunk resources/js/app.js is not a list',
        ];
        yield 'imports that are null' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "imports": null}}',
            'imports of chunk resources/js/app.js is not a list',
        ];
        yield 'an import of a chunk it does not have' => [
            '{"resources/js/app.js": {"file": "assets/app.js", "imports": ["_shared.js"]}}',
            'chunk _shared.js is missing',
        ];
    }

    #[DataProvider('invalidManifests')]
    public function testManifestThatIsNotValidIsAnError(string $json, string $reason): void
    {
        $manifest = $this->file('manifest.json', $json);

        self::assertSame(
            "Vite manifest $manifest is not valid: $reason",
            self::failure(fn() => (new Vite($manifest, '/build/'))->tags('resources/js/app.js')),
        );
    }

    public function testUnreadableBuildIsAnError(): void
    {
        $manifest = $this->file('manifest.json', '{}');
        chmod($manifest, 0);

        try {
            // Root reads it anyway
            if (is_readable($manifest)) {
                self::markTestSkipped('The current user can read files without permission bits.');
            }

            $vite = new Vite($manifest, '/build/');
            $message = "Cannot read Vite manifest $manifest: Failed to open stream: Permission denied";
            self::assertSame($message, self::failure(fn() => $vite->tags('a.js')));
            self::assertSame($message, self::failure($vite->version(...)));
        } finally {
            chmod($manifest, 0644);
        }
    }

    public function testWhileTheDevServerRunsEntriesLoadFromItWithTheViteClient(): void
    {
        self::assertSame(self::tags(
            '<script type="module" src="http://localhost:5173/@vite/client"></script>',
            '<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">',
            '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
        ), $this->devServer('http://localhost:5173')->tags('resources/css/app.css', 'resources/js/app.js'));
    }

    /** @return iterable<string, array{string}> */
    public static function stylesheets(): iterable
    {
        foreach (['css', 'less', 'sass', 'scss', 'styl', 'stylus', 'pcss', 'postcss', 'sss'] as $extension) {
            yield $extension => ["resources/css/app.$extension"];
        }
    }

    #[DataProvider('stylesheets')]
    public function testDevServerServesStylesheetsAsStylesheets(string $entry): void
    {
        self::assertStringEndsWith(
            '<link rel="stylesheet" href="http://localhost:5173/' . $entry . '">',
            $this->devServer('http://localhost:5173')->tags($entry),
        );
    }

    public function testDevServerServesOtherEntriesAsModules(): void
    {
        self::assertStringEndsWith(
            '<script type="module" src="http://localhost:5173/resources/js/app.css.ts"></script>',
            $this->devServer('http://localhost:5173')->tags('resources/js/app.css.ts'),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function devServerAddresses(): iterable
    {
        yield 'an origin' => ['http://localhost:5173', 'http://localhost:5173/'];
        yield 'with a trailing slash and a newline' => ["http://localhost:5173/\n", 'http://localhost:5173/'];
        yield 'with the base the sources are served at' => [
            'https://vite.example.test/assets/',
            'https://vite.example.test/assets/',
        ];
    }

    #[DataProvider('devServerAddresses')]
    public function testDevServerAddressIsTheRootOfItsUrls(string $address, string $root): void
    {
        self::assertSame(self::tags(
            '<script type="module" src="' . $root . '@vite/client"></script>',
            '<script type="module" src="' . $root . 'resources/js/app.js"></script>',
        ), $this->devServer($address)->tags('resources/js/app.js'));
    }

    public function testDevServerUrlsAreEncodedAndEscaped(): void
    {
        $tags = $this->devServer('http://localhost:5173/"><script>alert(1)</script>')->tags('resources/js/my page.js');

        self::assertStringNotContainsString('<script>alert(1)', $tags);
        self::assertStringEndsWith(
            '<script type="module" src="http://localhost:5173/&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;'
            . '/resources/js/my%20page.js"></script>',
            $tags,
        );
    }

    public function testDevServerNeedsNoBuild(): void
    {
        $this->file('hot', 'http://localhost:5173');
        $vite = new Vite($this->dir . '/manifest.json', '/build/', $this->dir . '/hot');

        self::assertStringEndsWith(
            '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
            $vite->tags('resources/js/app.js'),
        );
    }

    public function testDevServerAddressIsReadFromTheHotFile(): void
    {
        self::assertNull($this->build()->devServer());
        self::assertNull((new Vite(self::MANIFEST, '/build/'))->devServer());
        self::assertSame('http://localhost:5173', $this->devServer("http://localhost:5173/\n")->devServer());
    }

    public function testEmptyHotFileIsNoDevServer(): void
    {
        $build = $this->build()->tags('resources/js/app.js');

        self::assertSame($build, $this->devServer(" \n")->tags('resources/js/app.js'));
    }

    public function testWithoutAHotFileTheBuildLoadsWhileTheDevServerRuns(): void
    {
        $this->file('hot', 'http://localhost:5173');

        self::assertStringStartsWith(
            '<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">',
            (new Vite(self::MANIFEST, '/build/'))->tags('resources/js/app.js'),
        );
    }

    public function testVersionIsTheSameForTheSameBuild(): void
    {
        $version = $this->build()->version();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $version);
        self::assertSame($version, (new Vite(self::MANIFEST, '/other/'))->version());
    }

    public function testVersionChangesWithTheBuild(): void
    {
        $build = (string) file_get_contents(self::MANIFEST);
        $vite = new Vite($this->file('manifest.json', $build), '/build/');
        $before = $vite->version();

        $this->file('manifest.json', str_replace('app-B0eXWbkh.js', 'app-C2kq0Lzd.js', $build));

        self::assertNotSame($before, $vite->version());
    }

    public function testVersionChangesWhenTheDevServerStartsMovesOrStops(): void
    {
        $vite = $this->build();
        $build = $vite->version();

        $this->file('hot', 'http://localhost:5173');
        $devServer = $vite->version();
        $this->file('hot', 'http://localhost:5174');
        $moved = $vite->version();
        unlink($this->dir . '/hot');

        self::assertCount(3, array_unique([$build, $devServer, $moved]));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $devServer);
        self::assertSame($build, $vite->version());
    }

    public function testVersionIsEmptyWithoutABuildOrADevServer(): void
    {
        self::assertSame('', (new Vite($this->dir . '/manifest.json', '/build/', $this->dir . '/hot'))->version());
    }

    /** The fixture build, with a hot file the dev server has not written */
    private function build(): Vite
    {
        return new Vite(self::MANIFEST, '/build/', $this->dir . '/hot');
    }

    /** The fixture build, while the dev server runs at $address */
    private function devServer(string $address): Vite
    {
        $this->file('hot', $address);

        return $this->build();
    }

    /** @param array<string, array<string, mixed>> $chunks */
    private function manifest(array $chunks): string
    {
        return $this->file('manifest.json', json_encode($chunks, JSON_THROW_ON_ERROR));
    }

    private function file(string $name, string $contents): string
    {
        $path = "$this->dir/$name";
        file_put_contents($path, $contents);

        return $path;
    }

    private static function tags(string ...$tags): string
    {
        return implode('', $tags);
    }

    /** The message of the ViteException that $call throws */
    private static function failure(callable $call): string
    {
        try {
            $call();
        } catch (ViteException $e) {
            return $e->getMessage();
        }

        self::fail('No ViteException was thrown');
    }
}
