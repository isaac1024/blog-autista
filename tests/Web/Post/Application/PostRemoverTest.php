<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\PostRemover;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\CmsPostUnpublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PostRemoverTest extends TestCase
{
    public function testItTakesThePostOffTheSite(): void
    {
        $post = Post::publish(CmsPostPublishedMother::create());
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PostIdMother::create()))
            ->willReturn($post);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($post));

        (new PostRemover(CmsPostUnpublishedMother::create()))($repository);
    }

    public function testItDoesNothingWhenThePostIsNotOnTheSite(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        (new PostRemover(CmsPostUnpublishedMother::create()))($repository);
    }
}
