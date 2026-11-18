<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Shared\Domain\DomainError;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

/**
 * Finds the posts on the site among those given, read at once as their
 * cards show them; one off the site, or what can't be the id of one, is
 * left out.
 */
final readonly class PostsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, PostCardResponse> keyed by id
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new PostId($id);
            } catch (DomainError) {
                // Not the id of one: none on the site.
            }
        }

        $found = [];

        foreach ($postRepository->cardsOf($ids) as $card) {
            $found[$card->id] = PostCardResponse::fromCard($card);
        }

        return $found;
    }
}
