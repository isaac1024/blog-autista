<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SitePage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Domain\AuthorRepository;
use Web\External\Domain\ExternalResourceRepository as WebExternalResourceRepository;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Page\Infrastructure\Controller\PublishedPage;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostCard;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Redirect\Domain\RedirectRepository;
use Web\Settings\Domain\SettingsRepository;
use Web\Shared\Domain\Robots;
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\SiteExternalViews;
use Web\Shared\Infrastructure\Ui\SiteLayout;
use Web\Shared\Infrastructure\Ui\SiteMosaics;
use Web\Shared\Infrastructure\Ui\SitePostCards;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostCardMother;
use Web\Tests\Post\Domain\PostIdMother;
use Web\Tests\Shared\Infrastructure\Ui\PostUrls;

final class PublishedPageTest extends TestCase
{
    use PostUrls;

    private const string TARGET = '01a1b801-ce58-723d-8234-b76d9105d001';
    private const string HOME = '01a1b801-ce58-723d-8234-b76d9105d002';
    private const string MISSING = '01a1b801-ce58-723d-8234-b76d9105d003';

    public function testItResolvesCurrentAddressesOfPublishedLinkTargetsOnly(): void
    {
        $content = [
            'version' => 1,
            'blocks' => [[
                'type' => 'paragraph',
                'text' => [
                    ['text' => 'Destino', 'marks' => ['link'], 'page_id' => self::TARGET],
                    ['text' => 'Portada', 'marks' => ['link'], 'page_id' => self::HOME],
                    ['text' => 'No publicada', 'marks' => ['link'], 'page_id' => self::MISSING],
                ],
            ]],
        ];
        $page = Page::publish(CmsPagePublishedMother::create(content: $content));
        $target = Page::publish(CmsPagePublishedMother::create(id: self::TARGET, title: 'Destino', slug: 'destino'));
        $home = Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home));

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                if ('web_home' === $name) {
                    return '/';
                }

                $slug = $parameters['slug'] ?? null;

                return \is_string($slug) ? '/'.$slug.'.html' : '';
            },
        );
        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->expects(self::once())->method('findByIds')->with([])->willReturn([]);
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([
                PageIdMother::create(self::TARGET),
                PageIdMother::create(self::HOME),
                PageIdMother::create(self::MISSING),
            ]))
            ->willReturn([$target, $home]);

        $result = new PublishedPage($urlGenerator, new SitePage(new RichTextComponents()), $imageRepository, new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator), new SitePostCards(self::postUrls($urlGenerator), self::createStub(ImageRepository::class), self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)))), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class)), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::mosaics($urlGenerator, $imageRepository))(PageResponse::fromPage($page));

        self::assertSame([
            ['href' => '/destino.html', 'runs' => [['text' => 'Destino', 'bold' => false]]],
            ['href' => '/', 'runs' => [['text' => 'Portada', 'bold' => false]]],
            ['href' => null, 'runs' => [['text' => 'No publicada', 'bold' => false]]],
        ], $result->main[1]->children[0]->props['text']);
    }

    /**
     * Search engines may show its images large, whatever it allows them.
     */
    public function testItLetsSearchEnginesShowItsImagesLarge(): void
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/sobre-mi.html');
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $siteLayout = new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator), new SitePostCards(self::postUrls($urlGenerator), self::createStub(ImageRepository::class), self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)))), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class));
        $page = Page::publish(CmsPagePublishedMother::create(robots: Robots::NoindexFollow));

        $result = new PublishedPage($urlGenerator, new SitePage(new RichTextComponents()), $imageRepository, $siteLayout, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::mosaics($urlGenerator, $imageRepository))(PageResponse::fromPage($page));

        // Only a page they may list may have its images shown large.
        self::assertSame('noindex,follow', $result->robots);
        // Shared, it is a page of the site, not a post.
        self::assertNull($result->article);
    }

    /**
     * Its visits are counted, by the id it has on the site.
     */
    public function testItCountsTheVisitsOfThePage(): void
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/sobre-mi.html');
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $siteLayout = new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator), new SitePostCards(self::postUrls($urlGenerator), self::createStub(ImageRepository::class), self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)))), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class));
        $page = Page::publish(CmsPagePublishedMother::create(id: self::TARGET));

        $result = new PublishedPage($urlGenerator, new SitePage(new RichTextComponents()), $imageRepository, $siteLayout, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::mosaics($urlGenerator, $imageRepository))(PageResponse::fromPage($page));

        self::assertSame(['type' => 'page', 'id' => self::TARGET], $result->countedContent);
    }

    /**
     * A mosaic shows the latest posts it asks for as cards, their titles a
     * level below the heading before it; mosaics asking for the same posts
     * read them once, and one with no post isn't painted.
     */
    public function testItPaintsItsMosaicsWithTheLatestPostsTheyAskFor(): void
    {
        $tag = '01a1b801-ce58-723d-8234-b76d9105d010';
        $mosaic = ['type' => 'mosaic', 'limit' => 2, 'section_id' => null, 'author_id' => null, 'tag_ids' => [$tag]];
        $page = Page::publish(CmsPagePublishedMother::create(content: ['version' => 1, 'blocks' => [
            $mosaic,
            ['type' => 'heading', 'level' => 2, 'text' => [['text' => 'Lo último']]],
            $mosaic,
            ['type' => 'mosaic', 'limit' => 6, 'section_id' => CmsPostPublishedMother::SECTION, 'author_id' => null, 'tag_ids' => []],
        ]]));
        $posts = array_map(static fn (int $index): Post => Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::random()->value(), [
            'title' => 'Entrada '.$index,
            'slug' => 'entrada-'.$index,
            'revision' => 1,
        ] + CmsPostPublishedMother::body())), [1, 2]);
        $posts = array_map(PostCardMother::of(...), $posts);
        $postRepository = $this->createMock(PostRepository::class);
        // Each mosaic asking for other posts reads which; their cards are read once for all.
        $postRepository->expects(self::exactly(2))->method('listedIds')->willReturnCallback(
            static fn (PostSelection $selection, int $offset, int $limit): array => 0 === $offset && 2 === $limit && [$tag] === $selection->tagIds && null === $selection->sectionId && null === $selection->authorId ? array_map(static fn (PostCard $card): PostId => new PostId($card->id), $posts) : [],
        );
        $postRepository->expects(self::once())->method('cardsOf')->willReturnCallback(
            static function (array $ids) use ($posts): array {
                $asked = array_map(static fn (mixed $id): string => $id instanceof PostId ? $id->value() : '', $ids);

                return array_values(array_filter($posts, static fn (PostCard $card): bool => \in_array($card->id, $asked, true)));
            },
        );
        $urlGenerator = new RouteNameUrlGenerator();
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $siteLayout = new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class));

        $result = new PublishedPage($urlGenerator, new SitePage(new RichTextComponents()), $imageRepository, $siteLayout, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::mosaics($urlGenerator, $imageRepository, $postRepository))(PageResponse::fromPage($page));
        ComponentContracts::assertHonoured($result);

        $mosaics = PageComponents::find($result, 'Shared:PostCards', []);
        self::assertSame([[2, false], [3, false]], array_map(static fn ($component): array => [$component->props['headingLevel'], $component->props['wide']], $mosaics));
        $cards = $mosaics[0]->props['cards'];
        self::assertIsArray($cards);
        self::assertSame(['/vida-diaria/2026/11/07/entrada-1.html', '/vida-diaria/2026/11/07/entrada-2.html'], array_column($cards, 'url'));
    }

    /**
     * The page tells search engines what kind of page it is: contact, the
     * search, or any other.
     */
    #[DataProvider('pagesOfEveryKind')]
    public function testItTellsSearchEnginesWhatKindOfPageItIs(PageType $type, ?string $slug, string $schema, string $canonical): void
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $siteLayout = new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::createStub(AuthorRepository::class), self::createStub(PageRepository::class));
        $page = Page::publish(CmsPagePublishedMother::create(slug: $slug, type: $type));

        $result = new PublishedPage($urlGenerator, new SitePage(new RichTextComponents()), $imageRepository, $siteLayout, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), self::linkedContents($urlGenerator, $pageRepository), self::mosaics($urlGenerator, $imageRepository))(PageResponse::fromPage($page));

        $webPage = [
            '@type' => $schema,
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => 'Sobre mí',
            'description' => 'Quién escribe este blog sobre autismo y por qué.',
            'isPartOf' => ['@id' => RouteNameUrlGenerator::SITE.'/#website'],
            'inLanguage' => 'es',
        ];

        // Every page but the home one is a step away from it.
        self::assertSame(null === $slug ? [$webPage] : [
            $webPage + ['breadcrumb' => ['@id' => $canonical.'#breadcrumb']],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical.'#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => RouteNameUrlGenerator::SITE.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Sobre mí'],
                ],
            ],
        ], $result->structuredData);
    }

    /**
     * @return iterable<string, array{PageType, ?string, string, string}>
     */
    public static function pagesOfEveryKind(): iterable
    {
        yield 'the home page' => [PageType::Home, null, 'WebPage', RouteNameUrlGenerator::SITE.'/'];
        yield 'a page' => [PageType::Standard, 'sobre-mi', 'WebPage', RouteNameUrlGenerator::SITE.'/sobre-mi.html'];
        yield 'the contact page' => [PageType::Contact, 'contacto', 'ContactPage', RouteNameUrlGenerator::SITE.'/contacto.html'];
        yield 'the search page' => [PageType::Search, 'buscar', 'SearchResultsPage', RouteNameUrlGenerator::SITE.'/buscar.html'];
        yield 'a legal page' => [PageType::PrivacyPolicy, 'privacidad', 'WebPage', RouteNameUrlGenerator::SITE.'/privacidad.html'];
    }

    private static function linkedContents(UrlGeneratorInterface $urlGenerator, PageRepository $pageRepository): SiteLinkedContents
    {
        return new SiteLinkedContents(
            $urlGenerator,
            $pageRepository,
            self::createStub(PostRepository::class),
            self::createStub(TermRepository::class),
            self::createStub(AuthorRepository::class),
            new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)),
            new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator),
            new SitePostCards(self::postUrls($urlGenerator), self::createStub(ImageRepository::class), self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class))),
        );
    }

    /**
     * Mosaics of the posts given, none unless given.
     */
    private static function mosaics(UrlGeneratorInterface $urlGenerator, ImageRepository $imageRepository, ?PostRepository $postRepository = null): SiteMosaics
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        return new SiteMosaics(
            $postRepository ?? self::createStub(PostRepository::class),
            new SitePostCards(self::postUrls($urlGenerator), $imageRepository, self::createStub(TermRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class))),
            new PostCardsComponent($translator),
        );
    }
}
