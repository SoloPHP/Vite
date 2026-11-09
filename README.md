# Solo Vite

Vite for PHP without a framework: the tags that load your entries from the dev server while it runs and from the build otherwise, and a Twig function that prints them.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/vite.svg)](https://packagist.org/packages/solophp/vite)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Features

- **Dev server or build, told by a file** — while `vite` runs, a plugin of a few lines keeps its address in `public/hot`, and the entries load from it with hot module replacement. When it stops, the file goes and the build loads. PHP never calls the dev server.
- **Every chunk an entry needs** — from the build, an entry comes with its CSS, the CSS of every chunk it imports, directly or not, and a `modulepreload` link for each of those chunks, as Vite's [backend integration guide](https://vite.dev/guide/backend-integration) describes.
- **Several entries, shared chunks once** — the entries of a page go to one call, such as the app and the page component of a first Inertia visit.
- **Your build layout** — the manifest and the URL the build is served at are settings, a CDN included.
- **An asset version** — `version()` changes with every build and when the dev server starts or stops: Inertia's asset version, ready to use.
- **Failures are loud** — a missing build, an entry the build does not have or a manifest that is not valid raises `ViteException`, which names the file. Nothing is left out silently.
- **Safe markup** — file names are encoded as the browser encodes them, so each module runs once, and every URL is escaped for HTML.
- **No dependencies** — Twig is needed only for the Twig function.

## Installation

```sh
composer require solophp/vite
```

Requires PHP 8.3+, and Twig 3 for the Twig function. Any Vite that writes a manifest will do; since Vite 5 it is `.vite/manifest.json` in the build directory.

## Set up Vite

The build goes to `public/build` with a manifest, and the plugin at the end keeps the dev server's address in `public/hot` while it runs:

```js
// vite.config.js
import { rmSync, writeFileSync } from 'node:fs'
import { defineConfig } from 'vite'

export default defineConfig(({ command }) => ({
    // The build is served from public/build, and the dev server serves the sources at its root
    base: command === 'build' ? '/build/' : '/',
    // public/ is the web root PHP serves: Vite has no static files of its own to copy there
    publicDir: false,
    plugins: [hotFile()],
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        // rollupOptions before Vite 8
        rolldownOptions: {
            input: ['resources/css/app.css', 'resources/js/app.js'],
        },
    },
    server: {
        port: 5173,
    },
}))

/**
 * Holds the dev server's address in public/hot while it runs, for Solo\Vite\Vite. Without
 * server.origin, the address is http://localhost and the port the server is given, `vite --port`
 * included, and it becomes the origin, so that URLs in the CSS point at the dev server too.
 */
function hotFile(file = 'public/hot') {
    let origin

    return {
        name: 'hot-file',
        apply: 'serve',
        // Runs once Vite has merged the command line into the config
        config(config) {
            origin = config.server?.origin ?? `http://localhost:${config.server?.port ?? 5173}`

            // A port another process holds must fail rather than move: the origin names this one
            return { server: { origin, strictPort: true } }
        },
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                writeFileSync(file, origin)

                // Only a server that wrote the file removes it, so one that fails to start leaves
                // the running one's alone. Vite restarts the server with the config loaded anew:
                // one set of handlers per process is enough.
                const bound = Symbol.for('hot-file')
                if (process[bound]) {
                    return
                }
                process[bound] = true
                process.on('exit', () => rmSync(file, { force: true }))
                for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
                    process.on(signal, () => process.exit())
                }
            })
        },
    }
}
```

The dev server serves its scripts to pages from `localhost`, its subdomains and `127.0.0.1`, on any port. When PHP serves the page from another host, such as `shop.test`, allow it in `server.cors.origin`. When the browser reaches the dev server at another address than `localhost`, set `server.origin` to it: the plugin then writes that address.

Keep the build and the hot file out of Git:

```gitignore
/public/build/
/public/hot
```

## Load the entries

```php
use Solo\Vite\Vite;

$vite = new Vite(
    manifestPath: __DIR__ . '/public/build/.vite/manifest.json',
    baseUrl: '/build/',
    hotFile: __DIR__ . '/public/hot',
);
```

The base URL is the `base` Vite builds with: a path such as `/build/`, a URL on another host such as `https://cdn.example.com/build/` when the build is served from there, or an empty string or `./` for URLs relative to the page, as Vite's relative base.

Print the tags in the page's `<head>`, naming each entry as the build's `input` does, by its path from the Vite root:

```php
<?= $vite->tags('resources/css/app.css', 'resources/js/app.js') ?>
```

While the dev server runs, they load the Vite client, which brings hot module replacement, and the entries from the dev server:

```html
<script type="module" src="http://localhost:5173/@vite/client"></script>
<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">
<script type="module" src="http://localhost:5173/resources/js/app.js"></script>
```

Otherwise they load the build, as its manifest lists it. Here `app.js` imports two chunks, `shared` and `vendor`, and `shared` imports `vendor`:

```html
<link rel="stylesheet" href="/build/assets/app-Bb7ih_3w.css">
<link rel="stylesheet" href="/build/assets/app-6knr8Tro.css">
<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">
<link rel="stylesheet" href="/build/assets/shared-YbeUx0a8.css">
<script type="module" src="/build/assets/app-B0eXWbkh.js"></script>
<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">
<link rel="modulepreload" href="/build/assets/shared-LfwZkJ0t.js">
```

The tags are printed one after another, without line breaks.

### In production

A hot file of `null` loads the build even if a `public/hot` comes along with a deploy, and saves looking for it on every request:

```php
$vite = new Vite(
    manifestPath: __DIR__ . '/public/build/.vite/manifest.json',
    baseUrl: '/build/',
    hotFile: $debug ? __DIR__ . '/public/hot' : null,
);
```

## Twig

```php
use Solo\Vite\Twig\ViteExtension;

$twig->addExtension(new ViteExtension($vite));
```

```twig
{{ vite('resources/js/app.js') }}
{{ vite(['resources/css/app.css', 'resources/js/app.js']) }}
```

The function marks its output as safe HTML, so autoescaping leaves the tags as they are.

## Inertia

Pass `version()` as the asset version. After a build, or when the dev server starts or stops, a client that runs other assets reloads the page on its next visit:

```php
$inertia = new Inertia($rootView, version: $vite->version());
```

The client loads the page component of a first visit once the app asks for it. To load it with the app instead, its CSS included, add it to the entries in the root template:

```php
<?= $vite->tags('resources/js/app.js', "resources/js/Pages/{$page['component']}.svelte") ?>
```

## React

`@vitejs/plugin-react` needs the preamble of Fast Refresh before the entries when the page does not come from the dev server. `devServer()` gives its address while it runs:

```php
<?php if (($devServer = $vite->devServer()) !== null) : ?>
<script type="module">
    import RefreshRuntime from <?= json_encode("$devServer/@react-refresh", JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>

    RefreshRuntime.injectIntoGlobalHook(window)
    window.$RefreshReg$ = () => {}
    window.$RefreshSig$ = () => (type) => type
    window.__vite_plugin_react_preamble_installed__ = true
</script>
<?php endif ?>
<?= $vite->tags('resources/js/app.jsx') ?>
```

## Which tags, in which order

From the dev server, the Vite client comes first. Then each entry loads as a stylesheet when it is a `.css`, `.less`, `.sass`, `.scss`, `.styl`, `.stylus`, `.pcss`, `.postcss` or `.sss` file, and as a module script otherwise.

From the build, the tags follow the order of Vite's backend integration guide. For each entry in turn:

1. Stylesheets: the entry's CSS, then the CSS of each chunk it imports, directly or not, each chunk after the chunks it imports itself. A CSS entry is a stylesheet itself.
2. A module script for each JavaScript entry, which the build makes a `.js` or `.mjs` file.
3. A `modulepreload` link for each chunk the entries import, so the browser fetches it along with the entry rather than after it.

The stylesheets of all the entries come first, then their scripts, then the preloads. A chunk that several entries import is listed once, and an entry that another one imports loads by its own script, not a preload. Dynamic imports are left out: they load when the code asks for them.

## Nothing is cached

Every call reads the hot file and the manifest again. In exchange for reading a small file per call, a long-running process, such as a RoadRunner or FrankenPHP worker, picks up a new build and notices the dev server start and stop. Pass all the entries of a page to one call, so that the manifest is read once and the chunks they share are listed once.

## API

| Method | Behavior |
|---|---|
| `new Vite(string $manifestPath, string $baseUrl, ?string $hotFile = null)` | `$manifestPath` is the build's manifest. `$baseUrl` is the URL the build is served at, the `base` Vite builds with, with or without the trailing slash; `''` or `./` keep the URLs relative to the page. `$hotFile` holds the dev server's address while it runs; `null` always loads the build. A base URL with a query or a fragment throws `InvalidArgumentException`. |
| `tags(string ...$entries): string` | The tags that load the entries. No entries, no tags. |
| `devServer(): ?string` | The dev server's address from the hot file, such as `http://localhost:5173`, or `null` when it does not run. |
| `version(): string` | A hash of the dev server's address while it runs, otherwise of the manifest. Empty when there is neither. |
| `new ViteExtension(Vite $vite)` | The Twig function `vite()`, which takes an entry or a list of entries. |

## Failures

`tags()`, `version()` and `devServer()` raise `Solo\Vite\Exception\ViteException`, a `RuntimeException`, when:

- the hot file or the manifest cannot be read; the message gives the reason, such as `Permission denied`;
- an entry is not named by its path from the Vite root, as the build's `input` names it: `resources/js/app.js`, not `/resources/js/app.js` or `./resources/js/app.js`. This holds for `tags()` in both modes, so the name fails in development too;
- there is no manifest and the dev server does not run, for `tags()` only;
- the manifest is not a JSON object, or a chunk the tags need is not an object, has no `file`, or has `css` or `imports` that are not a list of names;
- the build does not have an entry, or makes it something other than a script or a stylesheet, such as an image.

Twig reports the exception as a `Twig\Error\RuntimeError` whose previous exception it is.

Nothing asks the dev server whether an entry exists: a mistyped entry shows up there as a 404 in the browser console, and on the build as a `ViteException`.

URLs are encoded as the browser encodes the URL of a module it imports, so a chunk loads under the same URL from a tag and from the chunks that import it, and runs once.

## Development

```sh
composer install
composer check
```

`composer check` validates `composer.json`, checks PSR-12, runs PHPStan at the maximum level and runs the tests. The tests read the manifest Vite 8.2 wrote for a sample app with shared chunks, a CSS entry and a dynamic import.

## License

MIT. See [LICENSE](LICENSE) and the [changelog](CHANGELOG.md).
