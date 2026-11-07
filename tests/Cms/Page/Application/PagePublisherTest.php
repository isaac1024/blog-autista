<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PagePublished;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
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
    public function testItPutsWhatTheFormHoldsOnTheSite(Page $page, Page $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        self::publisher($page->id()->value(), title: 'Sobre mí', slug: 'sobre-mi')($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Page, Page}>
     */
    public static function publishablePages(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'a draft, with its first edit' => [
            PageMother::create(id: $id, title: 'Borrador', slug: 'borrador', updatedAt: self::BEFORE),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi'),
        ];
        yield 'a draft as it is' => [
            PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION, updatedAt: self::BEFORE),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE),
        ];
        yield 'a published page with changes, keeping its first publication' => [
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', publishedTitle: 'Quién soy', firstPublishedAt: self::BEFORE, lastPublishedAt: self::BEFORE),
            PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', firstPublishedAt: self::BEFORE),
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

        self::publisher($id, title: 'Contacto', slug: 'contacto')($repository, $eventBus);
    }

    /**
     * @param array<array-key, mixed> $content
     */
    #[DataProvider('unpublishableEdits')]
    public function testItPublishesNothingWithoutEverythingTheSiteShows(Page $page, string $title, array $content, string $metaDescription, DomainError $error): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectExceptionObject($error);

        self::publisher($page->id()->value(), $title, 'sobre-mi', $content, $metaDescription)($repository, $eventBus);
    }

    /**
     * @return iterable<string, array{Page, string, array<array-key, mixed>, string, DomainError}>
     */
    public static function unpublishableEdits(): iterable
    {
        $draft = PageMother::create(title: 'Sobre mí', slug: 'sobre-mi');

        yield 'without content' => [$draft, 'Sobre mí', Text::EMPTY, PageMother::META_DESCRIPTION, InvalidPublication::withoutContent()];
        yield 'without a meta description' => [$draft, 'Sobre mí', PageMother::CONTENT, '', InvalidPublication::withoutMetaDescription()];
        yield 'with a blank meta description' => [$draft, 'Sobre mí', PageMother::CONTENT, "  \n ", InvalidPublication::withoutMetaDescription()];
        yield 'with an invalid title' => [$draft, 'Ab', PageMother::CONTENT, PageMother::META_DESCRIPTION, InvalidTitle::tooShort(3)];
        yield 'a published page as it is on the site' => [
            PageMother::published(title: 'Sobre mí', slug: 'sobre-mi'),
            'Sobre mí',
            PageMother::CONTENT,
            PageMother::META_DESCRIPTION,
            InvalidPublication::nothingToPublish(),
        ];
    }

    public function testItPublishesNothingWhenAnotherPageHasTheSlug(): void
    {
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'borrador');
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->method('findBySlug')->willReturn(PageMother::published(id: PageIdMother::random()->value(), slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectException(SlugAlreadyTaken::class);

        self::publisher($page->id()->value(), title: 'Sobre mí', slug: 'sobre-mi')($repository, $eventBus);
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        self::publisher($id, title: 'Sobre mí', slug: 'sobre-mi')($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        self::publisher('sobre-mi', title: 'Sobre mí', slug: 'sobre-mi')(self::createStub(PageRepository::class), self::createStub(EventBus::class));
    }

    /**
     * Publishing content and a meta description enough to publish, unless a
     * test says otherwise.
     *
     * @param array<array-key, mixed> $content
     */
    private static function publisher(
        string $id,
        string $title,
        string $slug,
        array $content = PageMother::CONTENT,
        string $metaDescription = PageMother::META_DESCRIPTION,
    ): PagePublisher {
        return new PagePublisher($id, $title, $slug, $content, $metaDescription, 'index,follow');
    }
}
