<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageDeleter;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\InvalidPagePublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageContentsLinked;
use Cms\Page\Domain\PageImagesUsed;
use Cms\Page\Domain\PagePagesLinked;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageUsage;
use Cms\Page\Domain\UniquePageCannotBeDeleted;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\RecordingTransaction;
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

        (new PageDeleter($id))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
    }

    #[DataProvider('uniquePages')]
    public function testItNeverDeletesAUniquePage(Page $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(UniquePageCannotBeDeleted::ofType($page->type()));

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
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

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
    }

    public function testItNeverDeletesAPublishedPage(): void
    {
        $page = PageMother::published();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidPagePublication::publishedPage());

        (new PageDeleter($page->id()->value()))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageDeleter($id))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        (new PageDeleter('page-id'))($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::unused());
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

        (new PageDeleter($id))($repository, $eventBus, new RecordingTransaction(), self::unused());
    }

    public function testItTellsNothingWhenThePageDeletedEmbedsNoImage(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with();

        (new PageDeleter(PageIdMother::DEFAULT_VALUE))($repository, $eventBus, new RecordingTransaction(), self::unused());
    }

    public function testItNeverDeletesAPageOtherPagesLinkTo(): void
    {
        $page = PageMother::unpublished();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');
        $pageUsage = $this->createMock(PageUsage::class);
        $pageUsage->expects(self::once())
            ->method('isInUse')
            ->with(self::equalTo($page->id()))
            ->willReturn(true);
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectExceptionObject(InvalidPagePublication::linkedPage());

        (new PageDeleter($page->id()->value()))($repository, $eventBus, new RecordingTransaction(), $pageUsage);
    }

    /**
     * A page deleted links to no page any longer, told at once.
     */
    public function testItTellsThePageLinksToNoPageOnceDeleted(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::unpublished(id: $id, content: Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105d002']]])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PagePagesLinked
                && $id === $event->aggregateId()
                && ['page_ids' => []] === $event->toPrimitives(),
        ));

        (new PageDeleter($id))($repository, $eventBus, new RecordingTransaction(), self::unused());
    }

    /**
     * The posts, terms, authors and external resources the text of a page
     * deleted linked to are freed, in one event that tells it links none.
     */
    public function testItFreesWhatTheTextOfAPageDeletedLinkedTo(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::unpublished(content: Text::stored(['type' => 'paragraph', 'text' => [
            ['text' => 'Un post', 'marks' => ['link'], 'post_id' => '01a1b801-ce58-723d-8234-b76d9105e101'],
            ['text' => ' una etiqueta', 'marks' => ['link'], 'term_id' => '01a1b801-ce58-723d-8234-b76d9105e201'],
            ['text' => ' una autora', 'marks' => ['link'], 'author_id' => '01a1b801-ce58-723d-8234-b76d9105e301'],
            ['text' => ' un vídeo', 'marks' => ['link'], 'external_id' => '01a1b801-ce58-723d-8234-b76d9105e401'],
        ]])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof PageContentsLinked && ['post_ids' => [], 'term_ids' => [], 'author_ids' => [], 'external_ids' => []] === $event->toPrimitives(),
        ));

        (new PageDeleter(PageIdMother::DEFAULT_VALUE))($repository, $eventBus, new RecordingTransaction(), self::unused());
    }

    /**
     * Deleting it and telling it uses nothing any longer happen within one
     * transaction, so a subscriber told at once that fails undoes the
     * deletion.
     */
    public function testItDeletesThePageAndTellsItUsesNothingWithinOneTransaction(): void
    {
        $transaction = new RecordingTransaction();
        $within = [];
        $note = static function () use ($transaction, &$within): void {
            $within[] = $transaction->isRunning();
        };
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::unpublished());
        $repository->method('delete')->willReturnCallback($note);
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback($note);

        (new PageDeleter(PageIdMother::DEFAULT_VALUE))($repository, $eventBus, $transaction, self::unused());

        self::assertSame([true, true], $within);
    }

    /**
     * No other page links to it.
     */
    private static function unused(): PageUsage
    {
        $pageUsage = self::createStub(PageUsage::class);
        $pageUsage->method('isInUse')->willReturn(false);

        return $pageUsage;
    }
}
