<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;

final class PagesOnSiteFinderTest extends TestCase
{
    private const string ABOUT = '01a19e72-b07f-7d78-889c-fa4a14a4fc01';
    private const string CONTACT = '01a19e72-b07f-7d78-889c-fa4a14a4fc02';
    private const string HOME = '01a19e72-b07f-7d78-889c-fa4a14a4fc03';

    /**
     * One query for every id, each once: what isn't an id never reaches it.
     */
    public function testItAsksTheRepositoryForEveryValidIdOnce(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([PageIdMother::create(self::CONTACT), PageIdMother::create(self::ABOUT)]))
            ->willReturn([]);

        new PagesOnSiteFinder([self::CONTACT, 'pagina', self::ABOUT, self::CONTACT])($repository);
    }

    /**
     * The site shows the title and address a page was published with, not
     * those edited since; the home page has no address of its own.
     */
    public function testItGivesThePagesOnTheSiteAsTheSiteServesThem(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByIds')->willReturn([
            PageMother::published(id: self::ABOUT, title: 'Quién soy', slug: 'sobre-mi', publishedTitle: 'Sobre mí'),
            PageMother::published(id: self::HOME, title: 'Portada', type: PageType::Home),
        ]);

        self::assertEquals([
            self::ABOUT => new PageOnSiteResponse('Sobre mí', 'sobre-mi'),
            self::HOME => new PageOnSiteResponse('Portada', null),
        ], new PagesOnSiteFinder([self::ABOUT, self::HOME])($repository));
    }

    /**
     * A page never published, or taken off, isn't on the site.
     */
    public function testItLeavesOutThePagesOffTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByIds')->willReturn([
            PageMother::create(id: self::ABOUT),
            PageMother::unpublished(id: self::CONTACT, slug: 'contacto'),
        ]);

        self::assertSame([], new PagesOnSiteFinder([self::ABOUT, self::CONTACT])($repository));
    }
}
