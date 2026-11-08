<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageImagesUsed;
use Cms\Page\Domain\PagePublished;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
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

        new PagePublisher($page->id()->value())($repository, self::createStub(EventBus::class));
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
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE, firstPublishedAt: self::BEFORE),
        ];
        yield 'the home page, which has no slug' => [
            PageMother::home(id: $id, content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION),
            PageMother::published(id: $id, title: 'Portada', slug: '', type: PageType::Home),
        ];
        yield 'an unpublished page' => [
            PageMother::unpublished(id: $id, title: 'Sobre mí', slug: 'sobre-mi'),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi'),
        ];
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
                && ClockMother::now()->format(\DATE_ATOM) === $event->occurredOn()->format(\DATE_ATOM)
                && [
                    'type' => 'contact',
                    'title' => 'Contacto',
                    'slug' => 'contacto',
                    'content' => PageMother::CONTENT,
                    'meta_description' => PageMother::META_DESCRIPTION,
                    'robots' => 'index,follow',
                    'first_published_at' => ClockMother::now(self::BEFORE)->format(\DATE_ATOM),
                    'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
                ] === $event->toPrimitives(),
        ));

        new PagePublisher($id)($repository, $eventBus);
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

        new PagePublisher($page->id()->value())($repository, $eventBus);
    }

    /**
     * @return iterable<string, array{Page, DomainError}>
     */
    public static function unpublishablePages(): iterable
    {
        yield 'a draft without a slug' => [
            PageMother::create(slug: '', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION),
            InvalidPublication::withoutSlug(),
        ];
        yield 'a draft without content' => [
            PageMother::create(metaDescription: PageMother::META_DESCRIPTION),
            InvalidPublication::withoutContent(),
        ];
        yield 'a draft without a meta description' => [
            PageMother::create(content: PageMother::CONTENT),
            InvalidPublication::withoutMetaDescription(),
        ];
        yield 'a published page saved without content' => [
            PageMother::published(content: RichTextMother::EMPTY, publishedContent: PageMother::CONTENT),
            InvalidPublication::withoutContent(),
        ];
        yield 'a published page saved without a meta description' => [
            PageMother::published(metaDescription: null, publishedMetaDescription: PageMother::META_DESCRIPTION),
            InvalidPublication::withoutMetaDescription(),
        ];
        yield 'a published page as it is on the site' => [
            PageMother::published(),
            InvalidPublication::nothingToPublish(),
        ];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        new PagePublisher($id)($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        new PagePublisher('sobre-mi')(self::createStub(PageRepository::class), self::createStub(EventBus::class));
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

        new PagePublisher($id)($repository, $eventBus);
    }
}
