<?php

declare(strict_types=1);

namespace Solo\Vite\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Solo\Vite\Exception\ViteException;
use Solo\Vite\Twig\ViteExtension;
use Solo\Vite\Vite;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

#[Group('twig')]
final class ViteExtensionTest extends TestCase
{
    private const string MANIFEST = __DIR__ . '/../Fixtures/public/build/.vite/manifest.json';

    public function testFunctionPrintsTheTagsOfAnEntryAsTheyAre(): void
    {
        self::assertSame(
            '<link rel="stylesheet" href="/build/assets/vendor-Biq9W0SU.css">'
            . '<script type="module" src="/build/assets/widget-DNEbAnU5.js"></script>'
            . '<link rel="modulepreload" href="/build/assets/vendor-BEl6AB2M.js">',
            self::render("{{ vite('resources/js/widget.js') }}"),
        );
    }

    public function testFunctionTakesTheEntriesOfAPageAsAList(): void
    {
        self::assertSame(
            (new Vite(self::MANIFEST, '/build/'))->tags('resources/css/app.css', 'resources/js/app.js'),
            self::render("{{ vite(['resources/css/app.css', 'resources/js/app.js']) }}"),
        );
    }

    public function testEntryTheBuildDoesNotHaveFailsTheTemplate(): void
    {
        try {
            self::render("{{ vite('resources/js/missing.js') }}");
            self::fail('The template rendered');
        } catch (RuntimeError $e) {
            self::assertInstanceOf(ViteException::class, $e->getPrevious());
        }
    }

    private static function render(string $template): string
    {
        $twig = new Environment(new ArrayLoader(['page.twig' => $template]), ['strict_variables' => true]);
        $twig->addExtension(new ViteExtension(new Vite(self::MANIFEST, '/build/')));

        return $twig->render('page.twig');
    }
}
