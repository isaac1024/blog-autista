<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostCreator;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostUpdater;
use Cms\Post\Domain\PostAuthorsUsed;
use Cms\Post\Domain\PostImagesUsed;
use Cms\Post\Domain\PostPagesLinked;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostTermsUsed;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostEditingTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItCreatesAnUnfiledDraftWithTheSlugOfItsTitle(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::create()));

        new PostCreator(PostIdMother::DEFAULT_VALUE, '  Mi primer   día sin ruido ')($repository, self::createStub(EventBus::class));
    }

    public function testItNeverCreatesAPostWithATitleTooShort(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.post.title.too_short', ['%min%' => 3], static fn () => new PostCreator(PostIdMother::DEFAULT_VALUE, 'No')($repository, self::createStub(EventBus::class)));
    }

    public function testItSavesWhatIsEditedWithTheTagsEachOnceInOrder(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create(updatedAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::create(
            title: 'Un título',
            slug: 'otro-slug',
            section: PostMother::SECTION,
            author: PostMother::AUTHOR,
            tags: [PostMother::OTHER_TAG, PostMother::TAG],
            content: PostMother::CONTENT,
            metaDescription: 'Un resumen',
        )));

        self::update($repository, self::createStub(EventBus::class), title: 'Un título', slug: 'otro-slug', section: PostMother::SECTION, author: PostMother::AUTHOR, tags: [PostMother::OTHER_TAG, PostMother::TAG, PostMother::OTHER_TAG], content: PostMother::CONTENT, metaDescription: 'Un resumen');
    }

    public function testItLeavesAPostWithoutSectionAuthorOrSlugWhenLeftBlank(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable());
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::create(slug: '', content: PostMother::CONTENT, metaDescription: PostMother::META_DESCRIPTION)));

        self::update($repository, self::createStub(EventBus::class), slug: '', content: PostMother::CONTENT, metaDescription: PostMother::META_DESCRIPTION);
    }

    public function testItTellsWhatThePostUsesWhenItChanges(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostImagesUsed && ['image_ids' => [self::IMAGE]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostPagesLinked && ['page_ids' => [self::PAGE]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostTermsUsed && ['term_ids' => [PostMother::SECTION, PostMother::TAG]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostAuthorsUsed && ['author_ids' => [PostMother::AUTHOR]] === $event->toPrimitives()),
        );

        self::update($repository, $eventBus, section: PostMother::SECTION, author: PostMother::AUTHOR, tags: [PostMother::TAG], content: RichTextMother::stored(
            ['type' => 'image', 'id' => self::IMAGE],
            ['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => self::PAGE]]],
        ));
    }

    public function testItTellsWhatThePublishedVersionStillUses(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::published(tags: [PostMother::TAG]));
        $eventBus = $this->createMock(EventBus::class);
        // The site still shows the section, the tag and the author: only the new tag is told.
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostTermsUsed && ['term_ids' => [PostMother::SECTION, PostMother::TAG, PostMother::OTHER_TAG]] === $event->toPrimitives()),
        );

        self::update($repository, $eventBus, section: '', author: '', tags: [PostMother::OTHER_TAG], content: PostMother::CONTENT, metaDescription: PostMother::META_DESCRIPTION);
    }

    #[DataProvider('unknownClassifiers')]
    public function testItNeverSavesAPostFiledOrSignedByWhatTheCmsHasNot(InMemoryClassifiers $classifiers, string $key): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $repository->expects(self::never())->method('save');

        self::assertTells($key, [], static fn () => self::update($repository, self::createStub(EventBus::class), section: PostMother::SECTION, author: PostMother::AUTHOR, tags: [PostMother::TAG], classifiers: $classifiers));
    }

    /**
     * @return iterable<string, array{InMemoryClassifiers, string}>
     */
    public static function unknownClassifiers(): iterable
    {
        yield 'a section that is not' => [new InMemoryClassifiers(sections: []), 'errors.post.classification.unknown_section'];
        yield 'an author that is not' => [new InMemoryClassifiers(authors: []), 'errors.post.classification.unknown_author'];
        yield 'a tag that is not' => [new InMemoryClassifiers(tags: []), 'errors.post.classification.unknown_tag'];
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        self::update($repository, self::createStub(EventBus::class));
    }

    /**
     * @param list<string>            $tags
     * @param array<array-key, mixed> $content
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private static function update(
        PostRepository $repository,
        EventBus $eventBus,
        string $title = PostMother::TITLE,
        string $slug = PostMother::SLUG,
        string $section = '',
        string $author = '',
        array $tags = [],
        array $content = RichTextMother::EMPTY,
        string $metaDescription = '',
        ?InMemoryClassifiers $classifiers = null,
    ): void {
        new PostUpdater(PostIdMother::DEFAULT_VALUE, $title, $slug, $section, $author, $tags, $content, $metaDescription, 'index,follow')(
            $repository,
            $eventBus,
            $classifiers ?? new InMemoryClassifiers(),
        );
    }
}
