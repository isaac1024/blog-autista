<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Post\Application\LatestPostsOnSiteFinder;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\PostCardsComponent;

/**
 * The cards of the posts each mosaic of a text shows, as a preview paints
 * them: the latest posts the CMS has on the site, keyed by what each mosaic
 * asks for, so mosaics asking for the same posts are read once. Each mosaic
 * reads its posts; their images and sections are then read once for all.
 *
 * @phpstan-import-type PostCard from PostCards
 */
final readonly class CmsMosaics
{
    public function __construct(
        private PostRepository $postRepository,
        private CmsPostCards $postCards,
        private PostCardsComponent $postCardsComponent,
    ) {}

    /**
     * @return array<string, list<PostCard>>
     */
    public function __invoke(RichText $content): array
    {
        $posts = [];

        foreach ($content->mosaics() as $mosaic) {
            $posts[$mosaic->key()] ??= new LatestPostsOnSiteFinder($mosaic->sectionId(), $mosaic->authorId(), $mosaic->tagIds(), $mosaic->limit())($this->postRepository);
        }

        if ([] === $posts) {
            return [];
        }

        $cards = $this->postCardsComponent->cards($this->postCards->cards(array_merge(...array_values($posts))));
        $mosaics = [];

        foreach ($posts as $key => $shown) {
            $mosaics[$key] = array_splice($cards, 0, \count($shown));
        }

        return $mosaics;
    }
}
