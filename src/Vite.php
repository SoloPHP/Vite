<?php

declare(strict_types=1);

namespace Solo\Vite;

use Solo\Vite\Exception\ViteException;
use Solo\Vite\Internal\Manifest;
use Solo\Vite\Internal\Tags;

/**
 * The tags that load Vite entries into a page.
 *
 * While the dev server runs, the hot file holds its address, and the entries load from it, with
 * hot module replacement. Otherwise they load from the build, as its manifest lists them.
 *
 * Nothing is cached: every call reads the hot file and the manifest again, so a process that
 * serves many requests picks up a new build, and a dev server that starts or stops.
 */
final readonly class Vite
{
    /** The prefix of the build's URLs: the base URL ending in one slash, or '' for relative URLs */
    private string $baseUrl;

    /**
     * @param string $manifestPath The build's manifest, such as public/build/.vite/manifest.json
     * @param string $baseUrl The URL the build is served at, the base Vite builds with: a path such as
     *     /build/, a URL on another host, or '' or ./ for URLs relative to the page
     * @param string|null $hotFile The file that holds the dev server's address while it runs, such as
     *     public/hot. Null loads the build even then, as production should.
     * @throws \InvalidArgumentException When the base URL has a query or a fragment, which the paths
     *     of the files would follow
     */
    public function __construct(
        private string $manifestPath,
        string $baseUrl,
        private ?string $hotFile = null,
    ) {
        if (strpbrk($baseUrl, '?#') !== false) {
            throw new \InvalidArgumentException("The Vite base URL $baseUrl must not have a query or a fragment");
        }

        $this->baseUrl = $baseUrl === '' ? '' : rtrim($baseUrl, '/') . '/';
    }

    /**
     * The tags that load the entries, each named by its path from the Vite root, as the build's
     * input names it: resources/js/app.js.
     *
     * From the build, an entry comes with its CSS and the CSS of every chunk it imports, directly
     * or not, and with a modulepreload link for each of those chunks, in the order Vite's backend
     * integration guide gives. Pass all the entries of a page to one call, so that the chunks they
     * share are loaded once.
     *
     * @throws ViteException When an entry is not named by its path from the Vite root, the hot file
     *     or the build cannot be read, the manifest is not valid, or it does not have an entry
     */
    public function tags(string ...$entries): string
    {
        if ($entries === []) {
            return '';
        }

        // Checked in both modes, so that a name the build would not have fails in development too
        foreach ($entries as $entry) {
            if ($entry === '' || str_starts_with($entry, '/') || str_starts_with($entry, './')) {
                throw new ViteException(
                    "Vite entry \"$entry\" is not a path from the Vite root, such as resources/js/app.js",
                );
            }
        }

        $entries = array_unique($entries);
        $devServer = $this->devServer();

        if ($devServer !== null) {
            return Tags::dev($devServer, $entries);
        }

        $json = $this->readManifest() ?? throw new ViteException(
            "Vite manifest $this->manifestPath not found: build the assets"
            . ($this->hotFile === null ? '' : ', or start the dev server'),
        );
        $manifest = new Manifest($this->manifestPath, $json);

        return Tags::build($manifest->files($entries), $this->baseUrl);
    }

    /**
     * The version of the assets a page loads, so that a client can tell it still runs other ones,
     * as Inertia's asset version does. It changes with every build, and when the dev server starts,
     * stops or moves. Empty when there is neither a build nor a dev server.
     *
     * @throws ViteException When the hot file or the manifest cannot be read
     */
    public function version(): string
    {
        $assets = $this->devServer() ?? $this->readManifest();

        return $assets === null ? '' : hash('xxh128', $assets);
    }

    /** The text of the manifest, or null when there is no build */
    private function readManifest(): ?string
    {
        return self::read($this->manifestPath, 'Vite manifest');
    }

    /**
     * The address of the dev server, from the hot file, or null when it does not run. Pages need it
     * for what tags() does not print, such as the preamble of React's Fast Refresh.
     *
     * @throws ViteException When the hot file cannot be read
     */
    public function devServer(): ?string
    {
        if ($this->hotFile === null) {
            return null;
        }

        $address = rtrim(trim(self::read($this->hotFile, 'Vite hot file') ?? ''), '/');

        return $address === '' ? null : $address;
    }

    /** The text of a file, or null when there is no such file */
    private static function read(string $path, string $what): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        error_clear_last();
        $text = @file_get_contents($path);
        if ($text === false) {
            // "file_get_contents(path): Failed to open stream: Permission denied" without its function
            $cause = (string) preg_replace('/^file_get_contents\(.*?\): /', '', error_get_last()['message'] ?? '');
            throw new ViteException("Cannot read $what $path" . ($cause === '' ? '' : ": $cause"));
        }

        return $text;
    }
}
