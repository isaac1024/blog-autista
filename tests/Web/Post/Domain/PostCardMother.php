<?php

declare(strict_types=1);

namespace Web\Tests\Post\Domain;

use Web\Post\Domain\Post;
use Web\Post\Domain\PostCard;

/**
 * Builds the card of a post for tests, as the repository reads it from what
 * the post saved has:
 *
 *     PostCardMother::of($post)
 */
final class PostCardMother
{
    public static function of(Post $post): PostCard
    {
        return new PostCard(
            $post->id()->value(),
            $post->title(),
            $post->slug(),
            $post->sectionId(),
            $post->sectionName(),
            $post->sectionSlug(),
            $post->authorId(),
            $post->authorName(),
            $post->openingImageId(),
            $post->metaDescription(),
            $post->day(),
            $post->firstPublishedAt(),
            $post->lastPublishedAt(),
        );
    }
}
