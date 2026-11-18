<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\IndexedPostsFinder;
use Web\Post\Application\PostCardResponse;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostCardMother;

final class IndexedPostsFinderTest extends TestCase
{
    /**
     * It asks for as many as it was given, and hands them over in the order
     * the repository reads them.
     */
    public function testItHandsOverTheLatestPostsSearchEnginesMayIndex(): void
    {
        $newest = Post::publish(CmsPostPublishedMother::create(id: '01a1e000-0000-7000-8000-000000000002', slug: 'otro'));
        $oldest = Post::publish(CmsPostPublishedMother::create());
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('indexed')->with(20)->willReturn([PostCardMother::of($newest), PostCardMother::of($oldest)]);

        self::assertEquals(
            [PostCardResponse::fromCard(PostCardMother::of($newest)), PostCardResponse::fromCard(PostCardMother::of($oldest))],
            (new IndexedPostsFinder(20))($repository),
        );
    }
}
