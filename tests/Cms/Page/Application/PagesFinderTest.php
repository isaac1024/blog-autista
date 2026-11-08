<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;

final class PagesFinderTest extends TestCase
{
    private const string ABOUT = '01a19e72-b07f-7d78-889c-fa4a14a4fb01';
    private const string CONTACT = '01a19e72-b07f-7d78-889c-fa4a14a4fb02';
    private const string MISSING = '01a19e72-b07f-7d78-889c-fa4a14a4fb03';

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

        (new PagesFinder([self::CONTACT, 'pagina', self::ABOUT, self::CONTACT]))($repository);
    }

    /**
     * In the order asked, whatever order the repository answers in; an id of
     * no page is left out.
     */
    public function testItGivesThePagesFoundInTheOrderAsked(): void
    {
        $about = PageMother::create(id: self::ABOUT, title: 'Sobre mí', slug: 'sobre-mi');
        $contact = PageMother::create(id: self::CONTACT, title: 'Contacto', slug: 'contacto');
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByIds')->willReturn([$about, $contact]);

        self::assertEquals(
            [PageResponse::fromPage($contact), PageResponse::fromPage($about)],
            (new PagesFinder([self::CONTACT, self::MISSING, self::ABOUT]))($repository),
        );
    }

    public function testItFindsNothingWithoutIds(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        self::assertSame([], (new PagesFinder([]))($repository));
    }
}
