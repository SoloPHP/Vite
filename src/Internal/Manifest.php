<?php

declare(strict_types=1);

namespace Solo\Vite\Internal;

use JsonException;
use Solo\Vite\Exception\ViteException;
use stdClass;

/**
 * A build's manifest, and the files that load its entries. The manifest must be a JSON object,
 * and each chunk an entry needs is checked as it is read.
 *
 * @internal
 * @phpstan-type Chunk array{file: string, css: list<string>, imports: list<string>}
 * @phpstan-type Files array{stylesheets: list<string>, scripts: list<string>, preloads: list<string>}
 */
final readonly class Manifest
{
    /** @var array<mixed> */
    private array $chunks;

    public function __construct(private string $manifestPath, string $json)
    {
        try {
            $manifest = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $this->invalid($e->getMessage(), $e);
        }

        if (!$manifest instanceof stdClass) {
            throw $this->invalid('it is not an object');
        }

        $this->chunks = get_object_vars($manifest);
    }

    /**
     * The files that load the entries: their stylesheets, scripts and preloads, each in order
     *
     * @param array<string> $entries
     * @return Files
     */
    public function files(array $entries): array
    {
        $stylesheets = [];
        $scripts = [];
        $preloads = [];
        // The chunks listed so far: the entries, and the chunks they import
        $listed = [];

        foreach ($entries as $entry) {
            if (!array_key_exists($entry, $this->chunks)) {
                throw new ViteException("Vite entry $entry is not in the manifest $this->manifestPath");
            }

            $listed[$entry] = true;
            $chunk = $this->chunk($entry);
            array_push($stylesheets, ...$chunk['css']);
            foreach ($this->importedChunks($chunk, $listed) as $import) {
                array_push($stylesheets, ...$import['css']);
                $preloads[] = $import['file'];
            }

            if (str_ends_with($chunk['file'], '.css')) {
                $stylesheets[] = $chunk['file'];
            } elseif (preg_match('/\.m?js$/', $chunk['file']) === 1) {
                $scripts[] = $chunk['file'];
            } else {
                throw new ViteException(
                    "Vite entry $entry is not a script or a stylesheet: the build makes it {$chunk['file']}",
                );
            }
        }

        // Two names in the manifest can stand for one file, as a CSS chunk and the JavaScript
        // chunk that lists it do
        $scripts = array_values(array_unique($scripts));

        return [
            'stylesheets' => array_values(array_unique($stylesheets)),
            'scripts' => $scripts,
            // An entry that another entry imports is loaded by its own script
            'preloads' => array_values(array_diff(array_unique($preloads), $scripts)),
        ];
    }

    /**
     * The chunks that $chunk imports, directly or not, each after the chunks it imports itself.
     * Those in $listed are left out, and those returned are added to it.
     *
     * @param Chunk $chunk
     * @param array<string, true> $listed
     * @return list<Chunk>
     */
    private function importedChunks(array $chunk, array &$listed): array
    {
        $chunks = [];
        foreach ($chunk['imports'] as $name) {
            if (isset($listed[$name])) {
                continue;
            }

            $listed[$name] = true;
            $import = $this->chunk($name);
            array_push($chunks, ...$this->importedChunks($import, $listed));
            $chunks[] = $import;
        }

        return $chunks;
    }

    /**
     * The chunk named $name, with the fields the tags need
     *
     * @return Chunk
     */
    private function chunk(string $name): array
    {
        if (!array_key_exists($name, $this->chunks)) {
            throw $this->invalid("chunk $name is missing");
        }

        $chunk = $this->chunks[$name];
        if (!$chunk instanceof stdClass) {
            throw $this->invalid("chunk $name is not an object");
        }

        $chunk = get_object_vars($chunk);
        $file = $chunk['file'] ?? null;
        if (!is_string($file) || $file === '') {
            throw $this->invalid("chunk $name has no file");
        }

        return [
            'file' => $file,
            'css' => $this->names($chunk, 'css', $name),
            'imports' => $this->names($chunk, 'imports', $name),
        ];
    }

    /**
     * The file or chunk names a chunk lists under $field, none when it has no such field
     *
     * @param array<mixed> $chunk
     * @return list<string>
     */
    private function names(array $chunk, string $field, string $name): array
    {
        $values = array_key_exists($field, $chunk) ? $chunk[$field] : [];
        if (!is_array($values) || !array_is_list($values)) {
            throw $this->invalid("$field of chunk $name is not a list");
        }

        $names = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw $this->invalid("$field of chunk $name is not a list of names");
            }
            $names[] = $value;
        }

        return $names;
    }

    private function invalid(string $reason, ?JsonException $previous = null): ViteException
    {
        return new ViteException("Vite manifest $this->manifestPath is not valid: $reason", previous: $previous);
    }
}
