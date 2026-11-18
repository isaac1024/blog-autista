<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostDeleter;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAuthorsUsed;
use Cms\Post\Domain\PostImagesUsed;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostTermsUsed;
use Cms\Post\Domain\PostUsage;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostDeleterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItDeletesAPostOffTheSiteAndTellsItUsesNothingAnyLonger(): void
    {
        $post = PostMother::unpublished();
        $repository = $this->repository($post);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($post));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostImagesUsed && ['image_ids' => []] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostTermsUsed && ['term_ids' => []] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostAuthorsUsed && ['author_ids' => []] === $event->toPrimitives()),
        );

        new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, $eventBus, new RecordingTransaction(), self::createStub(PostUsage::class));
    }

    public function testItNeverDeletesAPublishedPost(): void
    {
        $repository = $this->repository(PostMother::published());
        $repository->expects(self::never())->method('delete');

        self::assertTells('errors.post.publication.published_post', [], static fn () => new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(PostUsage::class)));
    }

    /**
     * A post another content links to stays, as a page does.
     */
    public function testItNeverDeletesAPostAnythingLinksTo(): void
    {
        $repository = $this->repository(PostMother::create());
        $repository->expects(self::never())->method('delete');
        $usage = self::createStub(PostUsage::class);
        $usage->method('isInUse')->willReturn(true);

        self::assertTells('errors.post.publication.used_post', [], static fn () => new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), $usage));
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(PostUsage::class));
    }

    /**
     * Deleting it and telling it uses nothing any longer happen within one
     * transaction, so a subscriber told at once that fails undoes the
     * deletion.
     */
    public function testItDeletesThePostAndTellsItUsesNothingWithinOneTransaction(): void
    {
        $transaction = new RecordingTransaction();
        $within = [];
        $note = static function () use ($transaction, &$within): void {
            $within[] = $transaction->isRunning();
        };
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::unpublished());
        $repository->method('delete')->willReturnCallback($note);
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback($note);

        new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, $eventBus, $transaction, self::createStub(PostUsage::class));

        self::assertSame([true, true], $within);
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
}
