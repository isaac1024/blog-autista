<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;

final class PostFinderTest extends TestCase
{
    public function testItFindsThePostWithTheGivenIdAsTheCmsShowsIt(): void
    {
        $post = PostMother::published(title: 'Después', publishedTitle: PostMother::TITLE);
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PostIdMother::create()))
            ->willReturn($post);

        $found = new PostFinder(PostIdMother::DEFAULT_VALUE)($repository);

        self::assertEquals(PostResponse::fromPost($post), $found);
        self::assertSame(['Después', 'published', true], [$found->title, $found->status, $found->changed]);
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostFinder(PostIdMother::DEFAULT_VALUE)($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('mi-post'));

        new PostFinder('mi-post')($repository);
    }
}
