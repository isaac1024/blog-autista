<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
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
            new PageResponse($id, 'Sobre mí', 'sobre-mi', type: 'standard', home: false, unique: false, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
            (new PageFinder($id))($repository),
        );
    }

    public function testItTellsTheHomePageApart(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::home(id: $id, title: 'Portada'));

        self::assertEquals(
            new PageResponse($id, 'Portada', '', type: 'home', home: true, unique: true, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
            (new PageFinder($id))($repository),
        );
    }

    public function testItTellsTheOtherUniquePagesApart(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact));

        self::assertEquals(
            new PageResponse($id, 'Contacto', 'contacto', type: 'contact', home: false, unique: true, content: RichTextMother::EMPTY, metaDescription: '', robots: 'index,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
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
            new PageResponse($id, 'Buscar', 'buscar', type: 'search', home: false, unique: true, content: $content, metaDescription: 'Busca en todo el blog.', robots: 'noindex,follow', status: 'draft', changed: false, publishedSlug: null, updatedAt: ClockMother::now()),
            (new PageFinder($id))($repository),
        );
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
