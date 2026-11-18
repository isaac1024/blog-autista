<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

/**
 * Finds the latest posts of a selection, as many as asked for at most,
 * newest first by the day they were first published, such as those a
 * mosaic shows.
 */
final readonly class LatestPostsFinder
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
     * @return list<PostCardResponse>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        return array_map(PostCardResponse::fromCard(...), $postRepository->listed($this->selection, 0, $this->limit));
    }
}
