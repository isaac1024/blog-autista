<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Ui;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Web\Image\Domain\ImageRepository;
use Web\Post\Application\PostsPageNotFound;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\SearchTerms;
use Web\Redirect\Domain\RedirectRepository;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\SitePostCards;
use Web\Shared\Infrastructure\Ui\SiteSearch;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class SiteSearchTest extends TestCase
{
    /**
     * The field comes first, holding what was searched; then what it found,
     * as cards, a page at a time, the next page searching the same.
     */
    public function testItPaintsThePostsFoundAPageAtATime(): void
    {
        $posts = array_map(static fn (int $index): Post => Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::random()->value(), [
            'title' => 'Entrada '.$index,
            'slug' => 'entrada-'.$index,
            'revision' => 1,
        ] + CmsPostPublishedMother::body())), range(1, 21));
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository->expects(self::once())->method('searched')
            ->with(self::equalTo(SearchTerms::fromText('ruido metro')), 0, 21)
            ->willReturn($posts);

        $page = $this->search($postRepository, ' Ruido, metro y RUIDO ', 1);
        ComponentContracts::assertHonoured($page);

        PageComponents::one($page, 'Shared:SearchForm', ['action' => '/buscar.html', 'name' => 'q', 'value' => ' Ruido, metro y RUIDO ']);
        /** @var list<array<string, mixed>> $cards */
        $cards = PageComponents::one($page, 'Shared:PostCards', ['headingLevel' => 2, 'wide' => false, 'lead' => true])->props['cards'];
        self::assertCount(20, $cards);
        self::assertSame('Entrada 1', $cards[0]['title']);
        PageComponents::one($page, 'Shared:Pagination', ['previousUrl' => null, 'nextUrl' => '/buscar.html?q=+Ruido%2C+metro+y+RUIDO+&pagina=2']);
        self::assertSame([], PageComponents::find($page, 'Shared:Notice', []));
    }

    public function testItOnlyOffersTheFieldUntilSomethingIsSearched(): void
    {
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository->expects(self::never())->method('searched');

        $page = $this->search($postRepository, '  ', 1);

        PageComponents::one($page, 'Shared:SearchForm', ['value' => '  ']);
        self::assertSame([], PageComponents::find($page, 'Shared:Notice', []));
        self::assertSame([], PageComponents::find($page, 'Shared:PostCards', []));
    }

    public function testItSaysWhenNoWordIsLongEnoughToSearchBy(): void
    {
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository->expects(self::never())->method('searched');

        $page = $this->search($postRepository, 'yo y tú', 1);

        PageComponents::one($page, 'Shared:Notice', ['message' => 'site.search.too_short(%min%=3)']);
    }

    public function testItSaysWhenNothingWasFound(): void
    {
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('searched')->willReturn([]);

        $page = $this->search($postRepository, ' elefante ', 1);

        PageComponents::one($page, 'Shared:Notice', ['message' => 'site.search.none(%text%=elefante)']);
        self::assertSame([], PageComponents::find($page, 'Shared:Pagination', []));
    }

    public function testItHasNoPageOfResultsPastTheLast(): void
    {
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('searched')->willReturn([]);

        $this->expectException(PostsPageNotFound::class);

        $this->search($postRepository, 'ruido', 2);
    }

    private function search(PostRepository $postRepository, string $text, int $number): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();

        return new SiteSearch(
            $postRepository,
            new SitePostCards($urlGenerator, self::createStub(ImageRepository::class), self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class))),
            new PostCardsComponent($translator),
            new ListPagination($translator, $urlGenerator),
            $translator,
        )(Page::create('Buscar'), 'buscar', $text, $number);
    }
}
