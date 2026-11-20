<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\PostSaver;
use Web\Post\Domain\AddressTaken;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PostSaverTest extends TestCase
{
    private const string LATER = '2026-11-08 11:00:00';

    public function testItPutsANewPostOnTheSite(): void
    {
        $event = CmsPostPublishedMother::create();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PostIdMother::create()))
            ->willReturn(null);
        $repository->method('findAt')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Post::publish($event)));

        (new PostSaver($event))($repository);
    }

    public function testItReplacesThePostOnTheSiteWithTheVersionPublishedLater(): void
    {
        $later = CmsPostPublishedMother::create(title: 'Otro título', sectionSlug: 'dia-a-dia', tagIds: [], metaDescription: null, lastPublishedAt: self::LATER);
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(Post::publish(CmsPostPublishedMother::create()));
        $repository->method('findAt')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Post::publish($later)));

        (new PostSaver($later))($repository);
    }

    public function testItKeepsItsAddressWhenTheAddressIsItsOwn(): void
    {
        $event = CmsPostPublishedMother::create();
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(Post::publish($event));
        $repository->expects(self::once())
            ->method('findAt')
            ->with(self::equalTo(new \DateTimeImmutable('2026-11-07')), 'mi-primer-dia')
            ->willReturn(Post::publish($event));
        $repository->expects(self::once())->method('save')->with(self::equalTo(Post::publish($event)));

        (new PostSaver($event))($repository);
    }

    public function testItRefusesAnAddressAnotherPostStillHas(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->method('findAt')->willReturn(Post::publish(CmsPostPublishedMother::create(id: PostIdMother::random()->value())));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(AddressTaken::atAddress('2026-11-07/mi-primer-dia'));

        (new PostSaver(CmsPostPublishedMother::create()))($repository);
    }
}
