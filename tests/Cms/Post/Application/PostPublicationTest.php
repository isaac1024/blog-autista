<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostChangesDiscarder;
use Cms\Post\Application\PostDeleter;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostPublisher;
use Cms\Post\Application\PostUnpublisher;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAuthorsUsed;
use Cms\Post\Domain\PostPublished;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostTermsUsed;
use Cms\Post\Domain\PostUnpublished;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostPublicationTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItPublishesAPostAtTheAddressOfItsSectionOnTheDayAndTellsTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable(tags: [PostMother::TAG]));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::published(tags: [PostMother::TAG])));
        $published = null;
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (DomainEvent ...$events) use (&$published): void {
            $published = $events;
        });
        $locks = new RecordingMutualExclusion();

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, $eventBus, new InMemoryClassifiers(), $locks);

        self::assertIsArray($published);
        self::assertCount(1, $published);
        self::assertInstanceOf(PostPublished::class, $published[0]);
        self::assertSame([
            'title' => PostMother::TITLE,
            'slug' => PostMother::SLUG,
            'section_id' => PostMother::SECTION,
            'section_name' => TermMother::NAME,
            'section_slug' => TermMother::SLUG,
            'author_id' => PostMother::AUTHOR,
            'author_name' => AuthorMother::NAME,
            'tag_ids' => [PostMother::TAG],
            'content' => PostMother::CONTENT,
            'meta_description' => PostMother::META_DESCRIPTION,
            'robots' => 'index,follow',
            'day' => '2026-11-07',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'revision' => 1,
        ], $published[0]->toPrimitives());
        self::assertSame(['post-address:vida-diaria/2026-11-07/'.PostMother::SLUG], $locks->lastHeld());
    }

    public function testItDatesTheAddressByTheDayOfMadrid(): void
    {
        // Past 23:00 UTC in winter it is the next day in Madrid.
        self::mockTime('2026-11-14 23:30:00 UTC');
        $locks = new RecordingMutualExclusion();

        new PostPublisher(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::publishable()), self::createStub(EventBus::class), new InMemoryClassifiers(), $locks);

        self::assertSame(['post-address:vida-diaria/2026-11-15/'.PostMother::SLUG], $locks->lastHeld());
    }

    public function testItKeepsTheDayOfTheFirstPublicationAndTakesTheSlugTheSectionHasNow(): void
    {
        $post = PostMother::published(publishedTitle: 'Antes', firstPublishedAt: '2026-10-01 10:00:00', lastPublishedAt: '2026-10-01 10:00:00');
        $locks = new RecordingMutualExclusion();
        $classifiers = new InMemoryClassifiers(sections: [PostMother::SECTION => ['name' => 'Día a día', 'slug' => 'dia-a-dia']]);

        new PostPublisher(PostIdMother::DEFAULT_VALUE)(self::stub($post), self::createStub(EventBus::class), $classifiers, $locks);

        self::assertSame(['post-address:dia-a-dia/2026-10-01/'.PostMother::SLUG], $locks->lastHeld());
        self::assertEquals(PostMother::published(sectionSlug: 'dia-a-dia', firstPublishedAt: '2026-10-01 10:00:00', publicationRevision: 2), $post);
    }

    #[DataProvider('incompletePosts')]
    public function testItNeverPublishesAPostWithoutWhatTheSiteShows(Post $post, string $key): void
    {
        $repository = $this->repository($post);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new RecordingMutualExclusion()));
    }

    /**
     * @return iterable<string, array{Post, string}>
     */
    public static function incompletePosts(): iterable
    {
        yield 'without a slug' => [PostMother::publishable(slug: ''), 'errors.post.publication.without_slug'];
        yield 'without a section' => [PostMother::publishable(section: ''), 'errors.post.publication.without_section'];
        yield 'without an author' => [PostMother::publishable(author: ''), 'errors.post.publication.without_author'];
        yield 'without content' => [PostMother::create(section: PostMother::SECTION, author: PostMother::AUTHOR, metaDescription: PostMother::META_DESCRIPTION), 'errors.post.publication.without_content'];
        yield 'without a meta description' => [PostMother::create(section: PostMother::SECTION, author: PostMother::AUTHOR, content: PostMother::CONTENT), 'errors.post.publication.without_meta_description'];
        yield 'published as it is' => [PostMother::published(), 'errors.post.publication.nothing_to_publish'];
    }

    #[DataProvider('classifiersOffTheSite')]
    public function testItNeverPublishesAPostWhoseSectionOrAuthorIsOffTheSite(InMemoryClassifiers $classifiers, string $key): void
    {
        $repository = $this->repository(PostMother::publishable());
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $classifiers, new RecordingMutualExclusion()));
    }

    /**
     * @return iterable<string, array{InMemoryClassifiers, string}>
     */
    public static function classifiersOffTheSite(): iterable
    {
        yield 'the section' => [new InMemoryClassifiers(sections: [PostMother::SECTION => null]), 'errors.post.publication.section_off_site'];
        yield 'the author' => [new InMemoryClassifiers(authors: [PostMother::AUTHOR => null]), 'errors.post.publication.author_off_site'];
    }

    public function testItPublishesWithTagsOffTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable(tags: [PostMother::TAG]));
        $repository->expects(self::once())->method('save');

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(tags: []), new RecordingMutualExclusion());
    }

    public function testItNeverPublishesAtTheAddressOfAnotherPostOnTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable());
        $repository->method('findOnSiteAt')->willReturn(PostMother::published(id: PostIdMother::random()->value()));
        $repository->expects(self::never())->method('save');
        $locks = new RecordingMutualExclusion();

        self::assertTells(
            'errors.post.address.taken',
            ['%slug%' => PostMother::SLUG, '%day%' => '2026-11-07'],
            static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), $locks),
        );
    }

    public function testItChecksAndSavesTheAddressWhileHoldingIt(): void
    {
        $locks = new RecordingMutualExclusion();
        $repository = $this->repository(PostMother::publishable());
        $repository->method('findOnSiteAt')->willReturnCallback(static function () use ($locks): ?Post {
            self::assertTrue($locks->isHolding());

            return null;
        });
        $repository->expects(self::once())->method('save')->willReturnCallback(static function () use ($locks): void {
            self::assertTrue($locks->isHolding());
        });

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), $locks);
    }

    public function testItRepublishesAtItsOwnAddress(): void
    {
        $post = PostMother::published(publishedTitle: 'Antes');
        $repository = $this->repository($post);
        $repository->method('findOnSiteAt')->willReturn($post);
        $repository->expects(self::once())->method('save');

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new RecordingMutualExclusion());
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

        new PostUnpublisher(PostIdMother::DEFAULT_VALUE)($repository, $eventBus);

        self::assertNull($post->publishedAddress());
    }

    public function testItNeverUnpublishesAPostOffTheSite(): void
    {
        self::assertTells('errors.post.publication.not_published', [], static fn () => new PostUnpublisher(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::create()), self::createStub(EventBus::class)));
    }

    public function testItDiscardsTheChangesBackToTheVersionOnTheSite(): void
    {
        $repository = $this->repository(PostMother::published(title: 'Después', publishedTitle: PostMother::TITLE, updatedAt: '2026-10-01 09:00:00'));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::published()));

        new PostChangesDiscarder(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));
    }

    public function testItHasNoChangesToDiscardOnAPostAsItIsOnTheSite(): void
    {
        self::assertTells('errors.post.publication.no_changes_to_discard', [], static fn () => new PostChangesDiscarder(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::published()), self::createStub(EventBus::class)));
    }

    public function testItDeletesAPostOffTheSiteAndTellsItUsesNothingAnyLonger(): void
    {
        $post = PostMother::unpublished();
        $repository = $this->repository($post);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($post));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostTermsUsed && ['term_ids' => []] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostAuthorsUsed && ['author_ids' => []] === $event->toPrimitives()),
        );

        new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, $eventBus);
    }

    public function testItNeverDeletesAPublishedPost(): void
    {
        $repository = $this->repository(PostMother::published());
        $repository->expects(self::never())->method('delete');

        self::assertTells('errors.post.publication.published_post', [], static fn () => new PostDeleter(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class)));
    }

    #[DataProvider('useCases')]
    public function testItThrowsWhenThePostDoesNotExist(\Closure $useCase): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        $useCase($repository);
    }

    /**
     * @return iterable<string, array{\Closure(PostRepository): void}>
     */
    public static function useCases(): iterable
    {
        $id = PostIdMother::DEFAULT_VALUE;

        yield 'publishing' => [static fn (PostRepository $repository) => new PostPublisher($id)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new RecordingMutualExclusion())];
        yield 'unpublishing' => [static fn (PostRepository $repository) => new PostUnpublisher($id)($repository, self::createStub(EventBus::class))];
        yield 'discarding' => [static fn (PostRepository $repository) => new PostChangesDiscarder($id)($repository, self::createStub(EventBus::class))];
        yield 'deleting' => [static fn (PostRepository $repository) => new PostDeleter($id)($repository, self::createStub(EventBus::class))];
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
