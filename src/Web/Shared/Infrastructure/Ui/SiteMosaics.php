<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Web\Post\Application\LatestPostIdsFinder;
use Web\Post\Application\PostsOnSiteFinder;
use Web\Post\Domain\PostRepository;

/**
 * The cards of the posts each mosaic of a text shows, as the site paints
 * them: the latest posts on the site it asks for, keyed by what it asks
 * for, so mosaics asking for the same posts are read once. Each mosaic
 * reads which posts it shows; their cards are then read once for all.
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
        $ids = [];

        foreach ($content->mosaics() as $mosaic) {
            $ids[$mosaic->key()] ??= new LatestPostIdsFinder($mosaic->sectionId(), $mosaic->authorId(), $mosaic->tagIds(), $mosaic->limit())($this->postRepository);
        }

        if ([] === $ids) {
            return [];
        }

        $posts = new PostsOnSiteFinder(array_values(array_unique(array_merge(...array_values($ids)))))($this->postRepository);
        $cards = array_combine(array_keys($posts), $this->postCardsComponent->cards(($this->postCards)(array_values($posts))));

        return array_map(
            static fn (array $shown): array => array_values(array_filter(array_map(static fn (string $id): ?array => $cards[$id] ?? null, $shown))),
            $ids,
        );
    }
}
