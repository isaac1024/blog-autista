<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\PostOnSiteFinder;
use Web\Post\Application\PostResponse;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PostOnSiteFinderTest extends TestCase
{
    public function testItHandsOverThePostWhenItIsOnTheSite(): void
    {
        $post = Post::publish(CmsPostPublishedMother::create());
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PostIdMother::create()))
            ->willReturn($post);

        self::assertEquals(PostResponse::fromPost($post), (new PostOnSiteFinder(PostIdMother::DEFAULT_VALUE))($repository));
    }

    public function testItFindsNothingWhenThePostIsNotOnTheSite(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        self::assertNull((new PostOnSiteFinder(PostIdMother::DEFAULT_VALUE))($repository));
    }
}
