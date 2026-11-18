<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

/**
 * Lists a page of the posts of a selection, such as those of a section or
 * of a tag, newest first by the day they were first published. The first
 * page may show none; any other must show some.
 */
final readonly class PostLister
{
    public const int PAGE_SIZE = 20;

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
        private int $page,
    ) {
        $this->selection = new PostSelection($sectionId, $authorId, $tagIds);
    }

    public function __invoke(PostRepository $postRepository): PostsPageResponse
    {
        if ($this->page < 1) {
            throw PostsPageNotFound::numbered($this->page);
        }

        // One more than a page tells whether another follows.
        $posts = $postRepository->listed($this->selection, ($this->page - 1) * self::PAGE_SIZE, self::PAGE_SIZE + 1);

        if ([] === $posts && $this->page > 1) {
            throw PostsPageNotFound::numbered($this->page);
        }

        return new PostsPageResponse(
            array_map(PostCardResponse::fromCard(...), \array_slice($posts, 0, self::PAGE_SIZE)),
            \count($posts) > self::PAGE_SIZE,
        );
    }
}
