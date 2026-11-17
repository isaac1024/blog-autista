<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostUpdater;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAuthorsUsed;
use Cms\Post\Domain\PostExternalsLinked;
use Cms\Post\Domain\PostImagesUsed;
use Cms\Post\Domain\PostPagesLinked;
use Cms\Post\Domain\PostPostsLinked;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostTermsUsed;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\InMemoryPostImages;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\RichText\InvalidRichText;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105e002';

    private const string POST = '01a1b801-ce58-723d-8234-b76d9105e101';

    private const string OTHER_POST = '01a1b801-ce58-723d-8234-b76d9105e102';

    private const string LINKED_AUTHOR = '01a1b801-ce58-723d-8234-b76d9105e201';

    private const string OTHER_AUTHOR = '01a1b801-ce58-723d-8234-b76d9105e202';

    private const string EXTERNAL = '01a1b801-ce58-723d-8234-b76d9105e301';

    private const string OTHER_EXTERNAL = '01a1b801-ce58-723d-8234-b76d9105e302';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
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

    /**
     * The posts its content links to are told, and the sections, tags and
     * authors it links to, together with those it is filed under and
     * signed by.
     */
    public function testItTellsWhatItsContentLinksToWithWhatItIsFiledUnder(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostPostsLinked && 'cms.post.posts_linked' === $event::eventName() && ['post_ids' => [self::PAGE]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostTermsUsed && ['term_ids' => [PostMother::SECTION, PostMother::OTHER_TAG]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostAuthorsUsed && ['author_ids' => [PostMother::AUTHOR]] === $event->toPrimitives()),
        );

        self::update($repository, $eventBus, section: PostMother::SECTION, author: '', tags: [], content: RichTextMother::stored(
            ['type' => 'paragraph', 'text' => [
                ['text' => 'Otro post', 'marks' => ['link'], 'post_id' => self::PAGE],
                ['text' => ' de esta etiqueta', 'marks' => ['link'], 'term_id' => PostMother::OTHER_TAG],
            ]],
            ['type' => 'widget', 'kind' => 'related_page', 'author_id' => PostMother::AUTHOR],
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

    /**
     * What the content on the site links to stays in use while the site
     * shows it, though the version edited links elsewhere: the pages, the
     * posts, the authors and the external resources of both are told.
     */
    public function testItTellsWhatTheContentOnTheSiteStillLinksTo(): void
    {
        $links = static fn (string $page, string $post, string $author, string $external): array => RichTextMother::stored(
            ['type' => 'paragraph', 'text' => [
                ['text' => 'Una página', 'marks' => ['link'], 'page_id' => $page],
                ['text' => ' un post', 'marks' => ['link'], 'post_id' => $post],
                ['text' => ' un autor', 'marks' => ['link'], 'author_id' => $author],
                ['text' => ' un vídeo', 'marks' => ['link'], 'external_id' => $external],
            ]],
        );
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::published(content: $links(self::PAGE, self::POST, self::LINKED_AUTHOR, self::EXTERNAL)));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostPagesLinked && ['page_ids' => [self::PAGE, self::OTHER_PAGE]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostPostsLinked && ['post_ids' => [self::POST, self::OTHER_POST]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostAuthorsUsed && ['author_ids' => [self::LINKED_AUTHOR, self::OTHER_AUTHOR, PostMother::AUTHOR]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostExternalsLinked && ['external_ids' => [self::EXTERNAL, self::OTHER_EXTERNAL]] === $event->toPrimitives()),
        );

        self::update($repository, $eventBus, section: PostMother::SECTION, author: PostMother::AUTHOR, tags: [], content: $links(self::OTHER_PAGE, self::OTHER_POST, self::OTHER_AUTHOR, self::OTHER_EXTERNAL), metaDescription: PostMother::META_DESCRIPTION);
    }

    /**
     * Another opening image is a change to publish, and the one the site
     * still opens with stays in use with it.
     */
    public function testItChangesTheImageItOpensWithKeepingTheOneOnTheSiteInUse(): void
    {
        $post = PostMother::published();
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn($post);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (Post $saved): bool => $saved->hasUnpublishedChanges() && self::IMAGE === $saved->openingImage()?->value()));
        $expected = [self::IMAGE, PostMother::OPENING_IMAGE];
        sort($expected);
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PostImagesUsed && ['image_ids' => $expected] === $event->toPrimitives()),
        );

        self::update($repository, $eventBus, section: PostMother::SECTION, author: PostMother::AUTHOR, content: PostMother::CONTENT, metaDescription: PostMother::META_DESCRIPTION, openingImage: self::IMAGE);
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

    /**
     * @param array<array-key, mixed> $content
     */
    #[DataProvider('selfLinks')]
    public function testItNeverSavesAPostThatLinksToItself(array $content): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.post.content.links_itself', [], static fn () => self::update($repository, self::createStub(EventBus::class), content: $content));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function selfLinks(): iterable
    {
        yield 'a link' => [RichTextMother::stored(['type' => 'paragraph', 'text' => [['text' => 'este post', 'marks' => ['link'], 'post_id' => PostIdMother::DEFAULT_VALUE]]])];
        yield 'a widget' => [RichTextMother::stored(['type' => 'widget', 'kind' => 'related_page', 'post_id' => PostIdMother::DEFAULT_VALUE])];
    }

    /**
     * Mosaics of posts are for pages and the bios of authors: a post shows
     * none.
     */
    public function testItNeverSavesAPostShowingAMosaic(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidRichText::notAllowed('A "mosaic" block'));

        self::update($repository, self::createStub(EventBus::class), content: RichTextMother::stored(['type' => 'mosaic', 'limit' => 6, 'section_id' => null, 'author_id' => null, 'tag_ids' => []]));
    }

    public function testItNeverSavesAPostOpeningWithAnImageTheLibraryHasNot(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.post.opening_image.unknown', [], static fn () => self::update($repository, self::createStub(EventBus::class), openingImage: self::IMAGE, images: new InMemoryPostImages()));
    }

    public function testItSavesAPostOpeningWithAnImageOffTheSite(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::create());
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (Post $saved): bool => self::IMAGE === $saved->openingImage()?->value()));

        self::update($repository, self::createStub(EventBus::class), openingImage: self::IMAGE, images: new InMemoryPostImages(onSite: [], drafts: [self::IMAGE]));
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
        string $openingImage = '',
        ?InMemoryPostImages $images = null,
    ): void {
        new PostUpdater(PostIdMother::DEFAULT_VALUE, $title, $slug, $section, $author, $tags, $openingImage, $content, $metaDescription, 'index,follow')(
            $repository,
            $eventBus,
            $classifiers ?? new InMemoryClassifiers(),
            $images ?? new InMemoryPostImages(drafts: [self::IMAGE]),
        );
    }
}
