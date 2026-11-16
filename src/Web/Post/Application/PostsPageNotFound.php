<?php

declare(strict_types=1);

namespace Web\Post\Application;

/**
 * A page of a list of posts that can't be: before the first, or past the
 * last, which shows no post.
 */
final class PostsPageNotFound extends \RuntimeException
{
    public static function numbered(int $page): self
    {
        return new self(\sprintf('The list of posts has no page %d.', $page));
    }
}
