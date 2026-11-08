<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageChangesDiscarder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageImagesUsed;
use Cms\Page\Domain\PagePagesLinked;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    public function testItBringsThePageBackToItsVersionOnTheSite(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(id: $id, title: 'Quién soy', publishedTitle: 'Sobre mí', updatedAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PageMother::published(id: $id, title: 'Sobre mí')));

        (new PageChangesDiscarder($id))($repository, self::createStub(EventBus::class));
    }

    #[DataProvider('pagesWithoutChanges')]
    public function testItDiscardsNothingWithoutUnpublishedChanges(Page $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidPublication::noChangesToDiscard());

        (new PageChangesDiscarder($page->id()->value()))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesWithoutChanges(): iterable
    {
        yield 'a draft' => [PageMother::create()];
        yield 'a published page as it is on the site' => [PageMother::published()];
        yield 'an unpublished page edited since' => [PageMother::unpublished(title: 'Quién soy')];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageChangesDiscarder($id))($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        (new PageChangesDiscarder('sobre-mi'))(self::createStub(PageRepository::class), self::createStub(EventBus::class));
    }

    public function testItTellsTheImagesThePageEmbedsOnceItsChangesAreDiscarded(): void
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
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PageImagesUsed
                && ['image_ids' => ['01a1b801-ce58-723d-8234-b76d9105d002']] === $event->toPrimitives(),
        ));

        new PageChangesDiscarder($id)($repository, $eventBus);
    }

    public function testItTellsThePagesThePageLinksToOnceItsChangesAreDiscarded(): void
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
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PagePagesLinked
                && ['page_ids' => ['01a1b801-ce58-723d-8234-b76d9105e002']] === $event->toPrimitives(),
        ));

        new PageChangesDiscarder($id)($repository, $eventBus);
    }
}
