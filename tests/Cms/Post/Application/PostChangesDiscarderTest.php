<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostChangesDiscarder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItDiscardsTheChangesBackToTheVersionOnTheSite(): void
    {
        $repository = $this->repository(PostMother::published(title: 'Después', publishedTitle: PostMother::TITLE, updatedAt: '2026-10-01 09:00:00'));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::published()));

        new PostChangesDiscarder(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    public function testItHasNoChangesToDiscardOnAPostAsItIsOnTheSite(): void
    {
        self::assertTells('errors.post.publication.no_changes_to_discard', [], static fn () => new PostChangesDiscarder(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::published()), self::createStub(EventBus::class), new RecordingTransaction()));
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostChangesDiscarder(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject&PostRepository
     */
    private function repository(Post $post): PostRepository
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn($post);

        return $repository;
    }

    private static function stub(Post $post): PostRepository
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn($post);

        return $repository;
    }
}
