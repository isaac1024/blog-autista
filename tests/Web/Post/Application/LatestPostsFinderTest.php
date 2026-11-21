<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\LatestPostsFinder;
use Web\Post\Application\PostCardResponse;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostCardMother;

final class LatestPostsFinderTest extends TestCase
{
    /**
     * It asks for the first ones of the selection, as many as it was given,
     * and hands them over in the order the repository reads them.
     */
    public function testItHandsOverTheLatestPostsOfTheSelection(): void
    {
        $card = PostCardMother::of(Post::publish(CmsPostPublishedMother::create()));
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('listed')->with(new PostSelection(null, CmsPostPublishedMother::AUTHOR, [CmsPostPublishedMother::TAG]), 0, 3)->willReturn([$card]);

        self::assertEquals([PostCardResponse::fromCard($card)], new LatestPostsFinder(null, CmsPostPublishedMother::AUTHOR, [CmsPostPublishedMother::TAG], 3)($repository));
    }
}
