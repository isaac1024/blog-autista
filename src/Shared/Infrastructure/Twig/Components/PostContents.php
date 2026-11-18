<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The contents of a post, by its parts, each leading to its heading: before
 * the text on small screens, and in a column beside it, which stays in view,
 * on large ones.
 */
#[AsTwigComponent]
final class PostContents
{
    /**
     * The anchor of the contents, which the links back to them lead to.
     */
    public const string ANCHOR = 'indice';

    /**
     * @var list<array{anchor: string, text: string}>
     */
    public array $entries = [];

    /**
     * @param list<array{anchor: string, text: string}> $entries the parts, in order, as HeadingAnchors::contents() gives them
     */
    public function mount(array $entries): void
    {
        $this->entries = $entries;
    }
}
