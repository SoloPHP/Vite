<?php

declare(strict_types=1);

namespace Solo\Vite\Twig;

use Solo\Vite\Vite;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The vite() function, which prints the tags that load one entry, {{ vite('resources/js/app.js') }},
 * or the entries of a list, {{ vite(['resources/css/app.css', 'resources/js/app.js']) }}.
 */
final class ViteExtension extends AbstractExtension
{
    public function __construct(private readonly Vite $vite)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite', $this->tags(...), ['is_safe' => ['html']]),
        ];
    }

    /** @param string|array<string> $entries */
    private function tags(string|array $entries): string
    {
        return $this->vite->tags(...array_values((array) $entries));
    }
}
