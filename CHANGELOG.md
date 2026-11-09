# Changelog

## 1.0.0 — Unreleased

### Added

- `Vite::tags()`: the tags that load one or more entries. While the dev server named in the hot file runs, the Vite client and the entries from it. Otherwise the build, as its manifest lists it: the CSS of each entry and of every chunk it imports, directly or not, the entry's script and a `modulepreload` link for each imported chunk, in the order of Vite's backend integration guide. Chunks that entries share are listed once.
- `Vite::devServer()`: the dev server's address from the hot file, or `null`, for what `tags()` does not print, such as React's Fast Refresh preamble.
- `Vite::version()`: a hash of the manifest, or of the dev server's address while it runs, for Inertia's asset version.
- `ViteException` for a hot file or build that cannot be read, with the reason; an entry not named by its path from the Vite root, in both modes; a manifest that is not valid; and an entry the build does not have or makes neither a script nor a stylesheet.
- `InvalidArgumentException` for a base URL with a query or a fragment.
- `Twig\ViteExtension`: the `vite()` function for Twig 3.

### Internal

- The tests read the manifest Vite 8.2 wrote for a sample app with shared chunks, an import of an import, a CSS entry and a dynamic import of a page that imports the app entry. CI runs them on PHP 8.3 to 8.5, with the lowest dependencies on 8.3, and without Twig.
