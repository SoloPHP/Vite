<?php

declare(strict_types=1);

namespace Solo\Vite\Internal;

/**
 * The HTML tags that load files, their URLs percent-encoded and escaped
 *
 * @internal
 * @phpstan-import-type Files from Manifest
 */
final class Tags
{
    /** The source files the dev server serves as stylesheets */
    private const string STYLESHEET = '/\.(css|less|sass|scss|styl|stylus|pcss|postcss|sss)$/';

    /**
     * The tags that load the build's files, each URL $baseUrl followed by the file's path
     *
     * @param Files $files
     */
    public static function build(array $files, string $baseUrl): string
    {
        $tags = '';
        foreach ($files['stylesheets'] as $file) {
            $tags .= self::link('stylesheet', $baseUrl . self::path($file));
        }
        foreach ($files['scripts'] as $file) {
            $tags .= self::script($baseUrl . self::path($file));
        }
        foreach ($files['preloads'] as $file) {
            $tags .= self::link('modulepreload', $baseUrl . self::path($file));
        }

        return $tags;
    }

    /**
     * The Vite client, which brings hot module replacement, and the entries, from the dev server
     *
     * @param array<string> $entries
     */
    public static function dev(string $devServer, array $entries): string
    {
        $tags = self::script("$devServer/@vite/client");
        foreach ($entries as $entry) {
            $url = "$devServer/" . self::path($entry);
            $tags .= preg_match(self::STYLESHEET, $entry) === 1 ? self::link('stylesheet', $url) : self::script($url);
        }

        return $tags;
    }

    /**
     * A file's path as the path of a URL, encoded as the browser encodes the path of a URL it
     * resolves: a chunk must load under the URL the chunks that import it name, or the browser
     * runs it a second time. Characters such as ( and @ stay as they are.
     */
    private static function path(string $file): string
    {
        return (string) preg_replace_callback(
            '/[\x00-\x20"#<>?`{}\x7F-\xFF]/',
            static fn(array $byte): string => sprintf('%%%02X', ord($byte[0])),
            $file,
        );
    }

    private static function link(string $rel, string $url): string
    {
        return '<link rel="' . $rel . '" href="' . self::escape($url) . '">';
    }

    private static function script(string $url): string
    {
        return '<script type="module" src="' . self::escape($url) . '"></script>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
