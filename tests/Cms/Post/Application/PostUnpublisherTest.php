<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostUnpublisher;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRedirects;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostUnpublished;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostUnpublisherTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItUnpublishesAPostAndTellsTheSite(): void
    {
        $post = PostMother::published();
        $repository = $this->repository($post);
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::unpublished()));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PostUnpublished && ['revision' => 2] === $event->toPrimitives(),
        ));

        new PostUnpublisher(PostIdMother::DEFAULT_VALUE)($repository, $eventBus, self::createStub(PostRedirects::class));

        self::assertNull($post->publishedAddress());
    }

    /**
     * A post redirects lead to stays on the site until they lead elsewhere.
     */
    public function testItKeepsOnTheSiteAPostRedirectsLeadTo(): void
    {
        $repository = $this->repository(PostMother::published());
        $repository->expects(self::never())->method('save');
        $redirects = self::createStub(PostRedirects::class);
        $redirects->method('isTargeted')->willReturn(true);

        self::assertTells('errors.post.publication.redirected', [], static fn () => new PostUnpublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $redirects));
    }

    public function testItNeverUnpublishesAPostOffTheSite(): void
    {
        self::assertTells('errors.post.publication.not_published', [], static fn () => new PostUnpublisher(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::create()), self::createStub(EventBus::class), self::createStub(PostRedirects::class)));
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostUnpublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(PostRedirects::class));
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
