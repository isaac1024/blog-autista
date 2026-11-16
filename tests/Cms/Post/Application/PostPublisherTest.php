<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostPublisher;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostPublished;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\InMemoryPostImages;
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

final class PostPublisherTest extends TestCase
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

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, $eventBus, new InMemoryClassifiers(), new InMemoryPostImages(), $locks);

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
            'opening_image_id' => PostMother::OPENING_IMAGE,
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

        new PostPublisher(PostIdMother::DEFAULT_VALUE)(self::stub(PostMother::publishable()), self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), $locks);

        self::assertSame(['post-address:vida-diaria/2026-11-15/'.PostMother::SLUG], $locks->lastHeld());
    }

    public function testItKeepsTheDayOfTheFirstPublicationAndTakesTheSlugTheSectionHasNow(): void
    {
        $post = PostMother::published(publishedTitle: 'Antes', firstPublishedAt: '2026-10-01 10:00:00', lastPublishedAt: '2026-10-01 10:00:00');
        $locks = new RecordingMutualExclusion();
        $classifiers = new InMemoryClassifiers(sections: [PostMother::SECTION => ['name' => 'Día a día', 'slug' => 'dia-a-dia']]);

        new PostPublisher(PostIdMother::DEFAULT_VALUE)(self::stub($post), self::createStub(EventBus::class), $classifiers, new InMemoryPostImages(), $locks);

        self::assertSame(['post-address:dia-a-dia/2026-10-01/'.PostMother::SLUG], $locks->lastHeld());
        self::assertEquals(PostMother::published(sectionSlug: 'dia-a-dia', firstPublishedAt: '2026-10-01 10:00:00', publicationRevision: 2), $post);
    }

    #[DataProvider('incompletePosts')]
    public function testItNeverPublishesAPostWithoutWhatTheSiteShows(Post $post, string $key): void
    {
        $repository = $this->repository($post);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), new RecordingMutualExclusion()));
    }

    /**
     * @return iterable<string, array{Post, string}>
     */
    public static function incompletePosts(): iterable
    {
        yield 'without a slug' => [PostMother::publishable(slug: ''), 'errors.post.publication.without_slug'];
        yield 'without a section' => [PostMother::publishable(section: ''), 'errors.post.publication.without_section'];
        yield 'without an author' => [PostMother::publishable(author: ''), 'errors.post.publication.without_author'];
        yield 'without an opening image' => [PostMother::publishable(openingImage: ''), 'errors.post.publication.without_opening_image'];
        yield 'without content' => [PostMother::create(section: PostMother::SECTION, author: PostMother::AUTHOR, metaDescription: PostMother::META_DESCRIPTION, openingImage: PostMother::OPENING_IMAGE), 'errors.post.publication.without_content'];
        yield 'without a meta description' => [PostMother::create(section: PostMother::SECTION, author: PostMother::AUTHOR, content: PostMother::CONTENT, openingImage: PostMother::OPENING_IMAGE), 'errors.post.publication.without_meta_description'];
        yield 'published as it is' => [PostMother::published(), 'errors.post.publication.nothing_to_publish'];
    }

    #[DataProvider('classifiersOffTheSite')]
    public function testItNeverPublishesAPostWhoseSectionOrAuthorIsOffTheSite(InMemoryClassifiers $classifiers, string $key): void
    {
        $repository = $this->repository(PostMother::publishable());
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $classifiers, new InMemoryPostImages(), new RecordingMutualExclusion()));
    }

    /**
     * @return iterable<string, array{InMemoryClassifiers, string}>
     */
    public static function classifiersOffTheSite(): iterable
    {
        yield 'the section' => [new InMemoryClassifiers(sections: [PostMother::SECTION => null]), 'errors.post.publication.section_off_site'];
        yield 'the author' => [new InMemoryClassifiers(authors: [PostMother::AUTHOR => null]), 'errors.post.publication.author_off_site'];
    }

    public function testItNeverPublishesAPostWhoseOpeningImageIsOffTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable());
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.post.publication.opening_image_off_site', [], static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages([]), new RecordingMutualExclusion()));
    }

    public function testItPublishesWithTagsOffTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable(tags: [PostMother::TAG]));
        $repository->expects(self::once())->method('save');

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(tags: []), new InMemoryPostImages(), new RecordingMutualExclusion());
    }

    public function testItNeverPublishesAtTheAddressOfAnotherPostOnTheSite(): void
    {
        $repository = $this->repository(PostMother::publishable());
        $repository->method('findOnSiteAt')->willReturn(PostMother::published(id: PostIdMother::random()->value()));
        $repository->expects(self::never())->method('save');
        $locks = new RecordingMutualExclusion();

        self::assertTells(
            'errors.post.address.taken',
            ['%section%' => TermMother::SLUG, '%date%' => '2026/11/07', '%slug%' => PostMother::SLUG],
            static fn () => new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), $locks),
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

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), $locks);
    }

    public function testItRepublishesAtItsOwnAddress(): void
    {
        $post = PostMother::published(publishedTitle: 'Antes');
        $repository = $this->repository($post);
        $repository->method('findOnSiteAt')->willReturn($post);
        $repository->expects(self::once())->method('save');

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), new RecordingMutualExclusion());
    }

    /**
     * Published at the address a post off the site last had, it takes it
     * from that one, which is saved without it, holding the address.
     */
    public function testItTakesTheLastAddressOfThePostOffTheSiteThatHadIt(): void
    {
        $former = PostMother::unpublished(id: '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0bff');
        $repository = $this->repository(PostMother::publishable());
        $repository->expects(self::once())->method('findOffSiteByLastAddress')->willReturn($former);
        $saved = [];
        $repository->expects(self::exactly(2))->method('save')->willReturnCallback(static function (Post $post) use (&$saved): void {
            $saved[] = $post->id()->value();
        });
        $lock = new RecordingMutualExclusion();

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), $lock);

        self::assertNull($former->lastAddress());
        self::assertSame([PostIdMother::DEFAULT_VALUE, '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0bff'], $saved);
    }

    /**
     * Its own last address it keeps, when it is published again there.
     */
    public function testItKeepsItsOwnLastAddress(): void
    {
        $post = PostMother::published(publishedTitle: 'Antes');
        $repository = $this->repository($post);
        $repository->method('findOffSiteByLastAddress')->willReturn($post);
        $repository->expects(self::once())->method('save');

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), new RecordingMutualExclusion());

        self::assertEquals($post->publishedAddress(), $post->lastAddress());
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostPublisher(PostIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new InMemoryClassifiers(), new InMemoryPostImages(), new RecordingMutualExclusion());
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
