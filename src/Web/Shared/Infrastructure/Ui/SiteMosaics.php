<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Web\Post\Application\LatestPostsFinder;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

/**
 * The cards of the posts each mosaic of a text shows, as the site paints
 * them: the latest posts on the site it asks for, keyed by what it asks
 * for, so mosaics asking for the same posts are read once.
 *
 * @phpstan-import-type PostCard from PostCards
 */
final readonly class SiteMosaics
{
    public function __construct(
        private PostRepository $postRepository,
        private SitePostCards $postCards,
        private PostCardsComponent $postCardsComponent,
    ) {}

    /**
     * @return array<string, list<PostCard>>
     */
    public function __invoke(RichText $content): array
    {
        $mosaics = [];

        foreach ($content->mosaics() as $mosaic) {
            $mosaics[$mosaic->key()] ??= $this->postCardsComponent->cards(($this->postCards)(
                new LatestPostsFinder(new PostSelection($mosaic->sectionId(), $mosaic->authorId(), $mosaic->tagIds()), $mosaic->limit())($this->postRepository),
            ));
        }

        return $mosaics;
    }
}
