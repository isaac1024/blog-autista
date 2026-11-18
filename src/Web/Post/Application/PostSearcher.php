<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;
use Web\Post\Domain\SearchTerms;
use Web\Post\Domain\SearchTooBroad;

/**
 * Searches the posts of the site by the words a reader writes, a page of
 * them at a time, as many as a list shows. A page past the first must show
 * some; nothing written, or no word long enough, has only the first page.
 */
final readonly class PostSearcher
{
    public function __construct(
        private string $text,
        private int $page,
    ) {}

    public function __invoke(PostRepository $postRepository): PostSearchResponse
    {
        if ($this->page < 1) {
            throw PostsPageNotFound::numbered($this->page);
        }

        $terms = SearchTerms::fromText($this->text);
        $asked = '' !== trim($this->text);

        try {
            $posts = $terms->isEmpty() ? [] : $postRepository->searched($terms, ($this->page - 1) * PostLister::PAGE_SIZE, PostLister::PAGE_SIZE + 1);
        } catch (SearchTooBroad) {
            return new PostSearchResponse(true, false, true, [], false);
        }

        if ([] === $posts && $this->page > 1) {
            throw PostsPageNotFound::numbered($this->page);
        }

        return new PostSearchResponse(
            $asked,
            $asked && $terms->isEmpty(),
            false,
            array_map(PostCardResponse::fromCard(...), \array_slice($posts, 0, PostLister::PAGE_SIZE)),
            \count($posts) > PostLister::PAGE_SIZE,
        );
    }
}
