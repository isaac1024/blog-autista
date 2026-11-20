<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;

/**
 * Finds the latest posts on the site of a selection, as many as asked for
 * at most, newest first by the day they were first published, as a preview
 * of a list or a mosaic paints them.
 */
final readonly class LatestPostsOnSiteFinder
{
    private PostSelection $selection;

    /**
     * @param ?string      $sectionId the section the posts are under, none for any
     * @param ?string      $authorId  who signs them, none for anyone
     * @param list<string> $tagIds    any of them, none for every post
     */
    public function __construct(
        ?string $sectionId,
        ?string $authorId,
        array $tagIds,
        private int $limit,
    ) {
        $this->selection = new PostSelection($sectionId, $authorId, $tagIds);
    }

    /**
     * @return list<PostOnSiteCardResponse>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $cards = [];

        foreach ($postRepository->latestOnSite($this->selection, $this->limit) as $post) {
            $card = PostOnSiteCardResponse::fromPost($post);

            if (null !== $card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }
}
