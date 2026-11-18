<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\PostCardsComponent;

/**
 * The cards of the posts each mosaic of a text shows, as a preview paints
 * them: the latest posts the CMS has on the site, keyed by what each mosaic
 * asks for, so mosaics asking for the same posts are read once.
 *
 * @phpstan-import-type PostCard from PostCards
 */
final readonly class CmsMosaics
{
    public function __construct(
        private CmsPostCards $postCards,
        private PostCardsComponent $postCardsComponent,
    ) {}

    /**
     * @return array<string, list<PostCard>>
     */
    public function __invoke(RichText $content): array
    {
        $mosaics = [];

        foreach ($content->mosaics() as $mosaic) {
            $mosaics[$mosaic->key()] ??= $this->postCardsComponent->cards(
                ($this->postCards)($mosaic->sectionId(), $mosaic->authorId(), $mosaic->tagIds(), $mosaic->limit()),
            );
        }

        return $mosaics;
    }
}
