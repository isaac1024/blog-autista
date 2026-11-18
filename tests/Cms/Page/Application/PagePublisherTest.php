<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\InvalidPagePublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageImagesUsed;
use Cms\Page\Domain\PagePagesLinked;
use Cms\Page\Domain\PagePublished;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RecordingTransaction;
use Shared\Tests\Domain\RichText\RichTextMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PagePublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishablePages')]
    public function testItPutsTheSavedVersionOnTheSite(Page $page, Page $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new PagePublisher($page->id()->value())($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{Page, Page}>
     */
    public static function publishablePages(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'a draft' => [
            PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION, updatedAt: self::BEFORE),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE),
        ];
        yield 'a published page with changes, keeping its first publication' => [
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', publishedTitle: 'Quién soy', updatedAt: self::BEFORE, firstPublishedAt: self::BEFORE, lastPublishedAt: self::BEFORE),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE, firstPublishedAt: self::BEFORE, publicationRevision: 2),
        ];
        yield 'the home page, which has no slug' => [
            PageMother::home(id: $id, content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION),
            PageMother::published(id: $id, title: 'Portada', slug: '', type: PageType::Home),
        ];
        yield 'an unpublished page' => [
            PageMother::unpublished(id: $id, title: 'Sobre mí', slug: 'sobre-mi'),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', publicationRevision: 3),
        ];
    }

    /**
     * Its address is its last from then on: the page off the site that last
     * had it loses it, so it no longer answers for what was there.
     */
    public function testItTakesTheLastAddressOfThePageOffTheSiteThatHadIt(): void
    {
        $page = PageMother::create(title: 'Sobre mí', slug: 'sobre-mi', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION);
        $former = PageMother::unpublished(id: '01a19e72-b07f-7d78-889c-fa4a14a4fb96', title: 'Retirada', slug: 'sobre-mi');
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(self::equalTo(SlugMother::create('sobre-mi')))->willReturn($former);
        $saved = [];
        $repository->expects(self::exactly(2))->method('save')->willReturnCallback(static function (Page $page) use (&$saved): void {
            $saved[] = [$page->id()->value(), $page->lastSiteSlug()?->value()];
        });

        new PagePublisher($page->id()->value())($repository, self::createStub(EventBus::class), new RecordingTransaction());

        self::assertSame([[$page->id()->value(), 'sobre-mi'], ['01a19e72-b07f-7d78-889c-fa4a14a4fb96', null]], $saved);
    }

    /**
     * The page found at its address may be itself, as it was before.
     */
    public function testItKeepsItsOwnLastAddress(): void
    {
        $page = PageMother::unpublished(title: 'Sobre mí', slug: 'sobre-mi');
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->method('findOffSiteByLastSiteSlug')->willReturn($page);
        $repository->expects(self::once())->method('save');

        new PagePublisher($page->id()->value())($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    public function testItTellsTheSiteWhatToServe(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(
            id: $id,
            title: 'Contacto',
            slug: 'contacto',
            type: PageType::Contact,
            publishedTitle: 'Escríbeme',
            firstPublishedAt: self::BEFORE,
            lastPublishedAt: self::BEFORE,
        ));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PagePublished
                && $id === $event->aggregateId()
                && [
                    'type' => 'contact',
                    'title' => 'Contacto',
                    'slug' => 'contacto',
                    'content' => PageMother::CONTENT,
                    'meta_description' => PageMother::META_DESCRIPTION,
                    'robots' => 'index,follow',
                    'first_published_at' => ClockMother::now(self::BEFORE)->format(\DATE_ATOM),
                    'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
                    'revision' => 2,
                ] === $event->toPrimitives(),
        ));

        new PagePublisher($id)($repository, $eventBus, new RecordingTransaction());
    }

    #[DataProvider('unpublishablePages')]
    public function testItPublishesNothingWithoutEverythingTheSiteShows(Page $page, DomainError $error): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectExceptionObject($error);

        new PagePublisher($page->id()->value())($repository, $eventBus, new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{Page, DomainError}>
     */
    public static function unpublishablePages(): iterable
    {
        yield 'a draft without a slug' => [
            PageMother::create(slug: '', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION),
            InvalidPagePublication::withoutSlug(),
        ];
        yield 'a draft without content' => [
            PageMother::create(metaDescription: PageMother::META_DESCRIPTION),
            InvalidPagePublication::withoutContent(),
        ];
        yield 'a draft without a meta description' => [
            PageMother::create(content: PageMother::CONTENT),
            InvalidPagePublication::withoutMetaDescription(),
        ];
        yield 'a published page saved without content' => [
            PageMother::published(content: RichTextMother::EMPTY, publishedContent: PageMother::CONTENT),
            InvalidPagePublication::withoutContent(),
        ];
        yield 'a published page saved without a meta description' => [
            PageMother::published(metaDescription: null, publishedMetaDescription: PageMother::META_DESCRIPTION),
            InvalidPagePublication::withoutMetaDescription(),
        ];
        yield 'a published page as it is on the site' => [
            PageMother::published(),
            InvalidPagePublication::nothingToPublish(),
        ];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        new PagePublisher($id)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        new PagePublisher('sobre-mi')(self::createStub(PageRepository::class), self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * Publishing replaces the version on the site, so the images it embedded
     * there and no longer does stop being in use.
     */
    public function testItTellsTheImagesThePageEmbedsOncePublished(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(
            id: $id,
            title: 'Sobre mí',
            slug: 'sobre-mi',
            content: Text::stored(['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d001']),
            publishedContent: Text::stored(['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d002']),
        ));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PageImagesUsed
                && $id === $event->aggregateId()
                && ['image_ids' => ['01a1b801-ce58-723d-8234-b76d9105d001']] === $event->toPrimitives()),
            self::isInstanceOf(PagePublished::class),
        );

        new PagePublisher($id)($repository, $eventBus, new RecordingTransaction());
    }

    /**
     * Publishing replaces the version on the site, so the pages it linked to
     * there and no longer does stop being in use.
     */
    public function testItTellsThePagesThePageLinksToOncePublished(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(
            id: $id,
            title: 'Sobre mí',
            slug: 'sobre-mi',
            content: Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105e001']]]),
            publishedContent: Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105e002']]]),
        ));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof PagePagesLinked
                && $id === $event->aggregateId()
                && ['page_ids' => ['01a1b801-ce58-723d-8234-b76d9105e001']] === $event->toPrimitives()),
            self::isInstanceOf(PagePublished::class),
        );

        new PagePublisher($id)($repository, $eventBus, new RecordingTransaction());
    }
}
