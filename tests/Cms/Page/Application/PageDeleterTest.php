<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageDeleter;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageImagesUsed;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\UniquePageCannotBeDeleted;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\RichText\RichTextMother as Text;

final class PageDeleterTest extends TestCase
{
    public function testItDeletesThePageWithTheGivenId(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $page = PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi');
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create($id)))
            ->willReturn($page);
        $repository->expects(self::once())
            ->method('delete')
            ->with(self::identicalTo($page));

        (new PageDeleter($id))($repository, self::createStub(EventBus::class));
    }

    #[DataProvider('uniquePages')]
    public function testItNeverDeletesAUniquePage(Page $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(UniquePageCannotBeDeleted::ofType($page->type()));

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function uniquePages(): iterable
    {
        yield 'home' => [PageMother::home()];

        foreach (PageMother::uniqueButHome() as $type => $page) {
            yield $type => [$page];
        }
    }

    public function testItDeletesAnUnpublishedPage(): void
    {
        $page = PageMother::unpublished();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($page));

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class));
    }

    public function testItNeverDeletesAPublishedPage(): void
    {
        $page = PageMother::published();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidPublication::publishedPage());

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class));
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageDeleter($id))($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        (new PageDeleter('page-id'))($repository, self::createStub(EventBus::class));
    }

    /**
     * A page deleted embeds no image any longer, told at once.
     */
    public function testItTellsThePageEmbedsNoImageOnceDeleted(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::unpublished(id: $id, content: Text::stored(['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d001'])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PageImagesUsed
                && $id === $event->aggregateId()
                && ['image_ids' => []] === $event->toPrimitives(),
        ));

        (new PageDeleter($id))($repository, $eventBus);
    }

    public function testItTellsNothingWhenThePageDeletedEmbedsNoImage(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with();

        (new PageDeleter(PageIdMother::DEFAULT_VALUE))($repository, $eventBus);
    }
}
