<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteTermPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Web\Author\Domain\AuthorRepository;
use Web\External\Domain\ExternalResourceRepository as WebExternalResourceRepository;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostsPageNotFound;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Redirect\Domain\RedirectRepository;
use Web\Settings\Domain\SettingsRepository;
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\SiteExternalViews;
use Web\Shared\Infrastructure\Ui\SiteLayout;
use Web\Shared\Infrastructure\Ui\SitePostCards;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Taxonomy\Infrastructure\Controller\PublishedTerm;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostCardMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PublishedTermTest extends TestCase
{
    private const string TERM = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    /**
     * The page of a section or a tag counts its visits as those of the term.
     */
    public function testItCountsTheVisitsOfTheTerm(): void
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $imageRepository = self::createStub(ImageRepository::class);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirects = new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class));
        $term = Term::publish(CmsTermPublished::fromPrimitives(self::TERM, [
            'taxonomy' => 'tag',
            'name' => 'Sueño',
            'slug' => 'sueno',
            'meta_description' => 'Sobre el sueño.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ]));

        $translator = new CatalogueTranslator();
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('listed')->willReturn([]);

        $page = new PublishedTerm(
            new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, $redirects, new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects, new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator), self::createStub(ImageRepository::class)), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class)),
            new SiteTermPage(new PostCardsComponent($translator), $translator),
            $postRepository,
            new SitePostCards($urlGenerator, $imageRepository, self::createStub(TermRepository::class), $redirects),
            new ListPagination($translator, $urlGenerator),
            $translator,
        )(TermResponse::fromTerm($term), 'web_tag', PostSelection::ofTag(self::TERM), 1);
        ComponentContracts::assertHonoured($page);

        self::assertSame(['type' => 'term', 'id' => self::TERM], $page->countedContent);
    }

    /**
     * Its posts are cards, newest first, a page of them at a time: the first
     * page is the term's own address, the next ones are numbered, titled by
     * their number and canonical at their own address.
     */
    public function testItPaintsAPageOfThePostsOfTheTermAsCards(): void
    {
        $posts = [];

        for ($index = 0; $index < 21; ++$index) {
            $posts[] = Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::random()->value(), [
                'title' => 'Entrada '.$index,
                'slug' => 'entrada-'.$index,
                'tag_ids' => [self::TERM],
                'revision' => 1,
            ] + CmsPostPublishedMother::body()));
        }

        $posts = array_map(PostCardMother::of(...), $posts);
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository->expects(self::exactly(2))->method('listed')->willReturnCallback(
            static fn (PostSelection $selection, int $offset, int $limit): array => \array_slice($posts, $offset, $limit),
        );

        $first = $this->publishedTerm($postRepository)(TermResponse::fromTerm(self::term()), 'web_tag', PostSelection::ofTag(self::TERM), 1);
        ComponentContracts::assertHonoured($first);

        self::assertSame('Sueño', $first->title);
        self::assertSame(RouteNameUrlGenerator::SITE.'/tag/sueno.html', $first->canonical);
        /** @var list<array<string, mixed>> $cards */
        $cards = PageComponents::one($first, 'Shared:PostCards', ['headingLevel' => 2, 'wide' => true, 'lead' => true])->props['cards'];
        self::assertCount(20, $cards);
        self::assertSame(['url' => '/vida-diaria/2026/11/07/entrada-0.html', 'title' => 'Entrada 0', 'excerpt' => 'Qué cambió el primer día con tapones.', 'section' => 'Vida diaria'], array_intersect_key($cards[0], array_flip(['url', 'title', 'excerpt', 'section'])));
        PageComponents::one($first, 'Shared:Pagination', ['previousUrl' => null, 'nextUrl' => '/tag/sueno.html?pagina=2']);

        $second = $this->publishedTerm($postRepository)(TermResponse::fromTerm(self::term()), 'web_tag', PostSelection::ofTag(self::TERM), 2);

        self::assertSame('site.posts.page_title(%name%=Sueño, %page%=2)', $second->title);
        self::assertSame(RouteNameUrlGenerator::SITE.'/tag/sueno.html?pagina=2', $second->canonical);
        /** @var list<array<string, mixed>> $secondCards */
        $secondCards = PageComponents::one($second, 'Shared:PostCards', [])->props['cards'];
        self::assertCount(1, $secondCards);
        PageComponents::one($second, 'Shared:Pagination', ['previousUrl' => '/tag/sueno.html', 'nextUrl' => null]);
        // Each page of the list is a collection of its own.
        self::assertSame([
            [
                '@type' => 'CollectionPage',
                '@id' => RouteNameUrlGenerator::SITE.'/tag/sueno.html?pagina=2#webpage',
                'url' => RouteNameUrlGenerator::SITE.'/tag/sueno.html?pagina=2',
                'name' => 'site.posts.page_title(%name%=Sueño, %page%=2)',
                'description' => 'Sobre el sueño.',
                'isPartOf' => ['@id' => RouteNameUrlGenerator::SITE.'/#website'],
                'inLanguage' => 'es',
                'breadcrumb' => ['@id' => RouteNameUrlGenerator::SITE.'/tag/sueno.html?pagina=2#breadcrumb'],
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => RouteNameUrlGenerator::SITE.'/tag/sueno.html?pagina=2#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => RouteNameUrlGenerator::SITE.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'site.posts.page_title(%name%=Sueño, %page%=2)'],
                ],
            ],
        ], $second->structuredData);
        // Search engines may show its images large.
        self::assertSame('index,follow,max-image-preview:large', $second->robots);
    }

    public function testItTellsATermWithoutPostsHasNoneYet(): void
    {
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('listed')->willReturn([]);

        $page = $this->publishedTerm($postRepository)(TermResponse::fromTerm(self::term()), 'web_tag', PostSelection::ofTag(self::TERM), 1);

        PageComponents::one($page, 'Shared:Notice', ['message' => 'site.posts.empty']);
        self::assertSame([], PageComponents::find($page, 'Shared:PostCards', []));
        self::assertSame([], PageComponents::find($page, 'Shared:Pagination', []));
    }

    /**
     * A page past the last one, or before the first, isn't there.
     */
    public function testItHasNoPageBeyondItsPosts(): void
    {
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('listed')->willReturn([]);

        $this->expectException(PostsPageNotFound::class);

        $this->publishedTerm($postRepository)(TermResponse::fromTerm(self::term()), 'web_tag', PostSelection::ofTag(self::TERM), 2);
    }

    private function publishedTerm(PostRepository $postRepository): PublishedTerm
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $imageRepository = self::createStub(ImageRepository::class);
        $pageRepository = self::createStub(PageRepository::class);
        $redirects = new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class));

        return new PublishedTerm(
            new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, $redirects, new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects, new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator), self::createStub(ImageRepository::class)), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class)),
            new SiteTermPage(new PostCardsComponent($translator), $translator),
            $postRepository,
            new SitePostCards($urlGenerator, $imageRepository, self::createStub(TermRepository::class), $redirects),
            new ListPagination($translator, $urlGenerator),
            $translator,
        );
    }

    private static function term(): Term
    {
        return Term::publish(CmsTermPublished::fromPrimitives(self::TERM, [
            'taxonomy' => 'tag',
            'name' => 'Sueño',
            'slug' => 'sueno',
            'meta_description' => 'Sobre el sueño.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ]));
    }
}
