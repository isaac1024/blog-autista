<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

/**
 * Finds the ids of the latest posts of a selection, as many as asked for at
 * most, newest first by the day they were first published, as
 * LatestPostsFinder finds their cards: for whoever reads the cards of
 * several selections at once, such as the mosaics of a page.
 */
final readonly class LatestPostIdsFinder
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
     * @return list<string>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        return array_map(static fn (PostId $id): string => $id->value(), $postRepository->listedIds($this->selection, 0, $this->limit));
    }
}
