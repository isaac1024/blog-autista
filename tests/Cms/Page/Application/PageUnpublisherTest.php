<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageUnpublisher;
use Cms\Page\Domain\InvalidPagePublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRedirects;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\PageUnpublished;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageUnpublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishedPages')]
    public function testItTakesThePageOffTheSite(Page $page): void
    {
        $id = $page->id()->value();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('save')->with(self::equalTo(PageMother::unpublished(id: $id, title: 'Sobre mí', slug: 'sobre-mi')));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PageUnpublished
                && $id === $event->aggregateId()
                && ['revision' => 2] === $event->toPrimitives(),
        ));

        (new PageUnpublisher($id))($repository, $eventBus, self::createStub(PageRedirects::class));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function publishedPages(): iterable
    {
        yield 'as it is on the site' => [PageMother::published(title: 'Sobre mí', slug: 'sobre-mi')];
    }

    #[DataProvider('pagesThatStay')]
    public function testItUnpublishesNothingButAPublishedGenericPage(Page $page, DomainError $error): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectExceptionObject($error);

        (new PageUnpublisher($page->id()->value()))($repository, $eventBus, self::createStub(PageRedirects::class));
    }

    /**
     * @return iterable<string, array{Page, DomainError}>
     */
    public static function pagesThatStay(): iterable
    {
        yield 'a draft' => [PageMother::create(), InvalidPagePublication::notPublished()];
        yield 'an unpublished page' => [PageMother::unpublished(), InvalidPagePublication::notPublished()];
        yield 'the published home page' => [PageMother::published(type: PageType::Home), InvalidPagePublication::uniquePage(PageType::Home)];

        foreach (PageType::uniqueTypes() as $type) {
            if (!$type->isHome()) {
                yield \sprintf('the published %s page', $type->value) => [
                    PageMother::published(slug: str_replace('_', '-', $type->value), type: $type),
                    InvalidPagePublication::uniquePage($type),
                ];
            }
        }
    }

    /**
     * While a redirect leads to it, the page stays on the site.
     */
    public function testItKeepsOnTheSiteAPageARedirectLeadsTo(): void
    {
        $page = PageMother::published();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');
        $pageRedirects = $this->createMock(PageRedirects::class);
        $pageRedirects->expects(self::once())->method('isTargeted')->with(self::equalTo($page->id()))->willReturn(true);

        $this->expectExceptionObject(InvalidPagePublication::redirected());

        (new PageUnpublisher($page->id()->value()))($repository, self::createStub(EventBus::class), $pageRedirects);
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageUnpublisher($id))($repository, self::createStub(EventBus::class), self::createStub(PageRedirects::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        (new PageUnpublisher('sobre-mi'))(self::createStub(PageRepository::class), self::createStub(EventBus::class), self::createStub(PageRedirects::class));
    }
}
