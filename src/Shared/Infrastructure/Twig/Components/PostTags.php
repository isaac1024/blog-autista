<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The tags of a post, each leading to its page.
 */
#[AsTwigComponent]
final class PostTags
{
    /**
     * @var list<array{name: string, url: string}>
     */
    public array $tags = [];

    /**
     * @param list<array{name: string, url: string}> $tags
     */
    public function mount(array $tags): void
    {
        $this->tags = $tags;
    }
}
