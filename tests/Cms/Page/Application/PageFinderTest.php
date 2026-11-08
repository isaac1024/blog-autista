<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;

final class PageFinderTest extends TestCase
{
    public function testItReturnsThePageWithTheRequestedId(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create($id)))
            ->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        self::assertEquals(
            new PageResponse($id, 'Sobre mí', 'sobre-mi', type: 'standard', home: false, unique: false, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, createdAt: ClockMother::now(), updatedAt: ClockMother::now(), hasSomethingToPublish: true, canBeUnpublished: false, canBeDeleted: true),
            (new PageFinder($id))($repository),
        );
    }

    public function testItTellsTheHomePageApart(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::home(id: $id, title: 'Portada'));

        self::assertEquals(
            new PageResponse($id, 'Portada', null, type: 'home', home: true, unique: true, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, createdAt: ClockMother::now(), updatedAt: ClockMother::now(), hasSomethingToPublish: true, canBeUnpublished: false, canBeDeleted: false),
            (new PageFinder($id))($repository),
        );
    }

    public function testItTellsTheOtherUniquePagesApart(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact));

        self::assertEquals(
            new PageResponse($id, 'Contacto', 'contacto', type: 'contact', home: false, unique: true, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, createdAt: ClockMother::now(), updatedAt: ClockMother::now(), hasSomethingToPublish: true, canBeUnpublished: false, canBeDeleted: false),
            (new PageFinder($id))($repository),
        );
    }

    public function testItHandsOverTheContentAndTheSearchEngineSettings(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $content = RichTextMother::stored(RichTextMother::heading(2, 'Buscar'), RichTextMother::paragraph('Encuentra lo que buscas.'));
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(
            id: $id,
            title: 'Buscar',
            slug: 'buscar',
            type: PageType::Search,
            content: $content,
            metaDescription: 'Busca en todo el blog.',
        ));

        self::assertEquals(
            new PageResponse($id, 'Buscar', 'buscar', type: 'search', home: false, unique: true, content: $content, metaDescription: 'Busca en todo el blog.', robots: 'noindex,follow', status: 'draft', changed: false, publishedSlug: null, createdAt: ClockMother::now(), updatedAt: ClockMother::now(), hasSomethingToPublish: true, canBeUnpublished: false, canBeDeleted: false),
            (new PageFinder($id))($repository),
        );
    }

    public function testItTellsWhereThePageStandsOnTheSite(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(id: $id, title: 'Quién soy', slug: 'sobre-mi', publishedTitle: 'Sobre mí', updatedAt: '2026-11-08 12:00:00'));

        $response = (new PageFinder($id))($repository);

        self::assertSame('published', $response->status);
        self::assertTrue($response->changed);
        self::assertSame('sobre-mi', $response->publishedSlug);
        self::assertEquals(new \DateTimeImmutable('2026-11-08 12:00:00'), $response->updatedAt);
    }

    public function testItTellsAnUnpublishedPageHasNoAddressOnTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::unpublished());

        $response = (new PageFinder(PageIdMother::DEFAULT_VALUE))($repository);

        self::assertSame('unpublished', $response->status);
        self::assertFalse($response->changed);
        self::assertNull($response->publishedSlug);
    }

    #[DataProvider('pagesAndTheirActions')]
    public function testItTellsWhatCanBeDoneToThePage(Page $page, bool $hasSomethingToPublish, bool $canBeUnpublished, bool $canBeDeleted, bool $changed): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn($page);

        $response = (new PageFinder(PageIdMother::DEFAULT_VALUE))($repository);

        self::assertSame(
            [$hasSomethingToPublish, $canBeUnpublished, $canBeDeleted, $changed],
            [$response->hasSomethingToPublish, $response->canBeUnpublished, $response->canBeDeleted, $response->changed],
        );
    }

    /**
     * Publishing, unpublishing, deleting, and discarding changes, which only
     * a page on the site with another version has.
     *
     * @return iterable<string, array{Page, bool, bool, bool, bool}>
     */
    public static function pagesAndTheirActions(): iterable
    {
        yield 'a draft' => [PageMother::create(), true, false, true, false];
        yield 'a published page as it is on the site' => [PageMother::published(), false, true, false, false];
        yield 'a published page with changes' => [PageMother::published(title: 'Quién soy', publishedTitle: 'Sobre mí'), true, true, false, true];
        yield 'an unpublished page' => [PageMother::unpublished(), true, false, true, false];
        yield 'an unpublished page edited since' => [PageMother::unpublished(title: 'Quién soy', publishedTitle: 'Sobre mí'), true, false, true, false];
        yield 'a unique draft' => [PageMother::create(type: PageType::Contact), true, false, false, false];
        yield 'a unique published page' => [PageMother::published(type: PageType::Contact), false, false, false, false];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageFinder($id))($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        (new PageFinder('page-id'))($repository);
    }
}
