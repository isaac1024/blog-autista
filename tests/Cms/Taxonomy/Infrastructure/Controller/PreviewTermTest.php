<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Shared\Infrastructure\Http\ExternalImageUrl;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Shared\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Shared\Infrastructure\Ui\CmsExternalViews;
use Cms\Shared\Infrastructure\Ui\CmsPostCards;
use Cms\Shared\Infrastructure\Ui\CmsSectionSlugs;
use Cms\Shared\Infrastructure\Ui\PreviewLayout;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Infrastructure\Controller\PreviewTerm;
use Cms\Taxonomy\Infrastructure\Controller\TaxonomySegment;
use Cms\Taxonomy\Infrastructure\Controller\TermAddress;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteTermPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\HttpFoundation\UriSigner;

final class PreviewTermTest extends TestCase
{
    /**
     * A section or a tag previews the first page of its posts on the site,
     * newest first, as many as the site lists on a page, and no navigation
     * between pages.
     */
    #[DataProvider('terms')]
    public function testItPreviewsTheFirstPageOfThePostsOfTheTermOnTheSite(TaxonomySegment $segment, Taxonomy $taxonomy, PostSelection $selection): void
    {
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository->expects(self::once())->method('latestOnSite')->with(self::equalTo($selection), 20)->willReturn([
            PostMother::published(id: PostIdMother::random()->value(), title: 'Un día', slug: 'un-dia'),
        ]);

        $page = $this->preview($postRepository)($segment, TermResponse::fromTerm(TermMother::published(taxonomy: $taxonomy)));
        ComponentContracts::assertHonoured($page);

        $cards = PageComponents::one($page, 'Shared:PostCards', ['headingLevel' => 2])->props['cards'];
        self::assertIsArray($cards);
        self::assertSame(['Un día'], array_column($cards, 'title'));
        self::assertSame([], PageComponents::find($page, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{TaxonomySegment, Taxonomy, PostSelection}>
     */
    public static function terms(): iterable
    {
        yield 'a section' => [TaxonomySegment::Sections, Taxonomy::Section, new PostSelection(PostMother::SECTION, null, [])];
        yield 'a tag' => [TaxonomySegment::Tags, Taxonomy::Tag, new PostSelection(null, null, [PostMother::SECTION])];
    }

    public function testItTellsATermWithoutPostsOnTheSiteHasNoneYet(): void
    {
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('latestOnSite')->willReturn([]);

        $page = $this->preview($postRepository)(TaxonomySegment::Sections, TermResponse::fromTerm(TermMother::published()));

        PageComponents::one($page, 'Shared:Notice', ['message' => 'site.posts.empty']);
    }

    private function preview(PostRepository $postRepository): PreviewTerm
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $redirectsByOrigin = new CmsRedirectsByOrigin(self::createStub(RedirectRepository::class));
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('findByIds')->willReturn([TermMother::published()]);
        $linkedContents = new CmsLinkedContents($urlGenerator, $pageRepository, $postRepository, $termRepository, self::createStub(AuthorRepository::class), $redirectsByOrigin, new CmsExternalViews(self::createStub(ExternalResourceRepository::class), $pageRepository, $urlGenerator, new ExternalImageUrl($urlGenerator, new SignedUrls(new UriSigner('secret')))), new CmsPostCards(self::createStub(PostRepository::class), self::createStub(ImageRepository::class), $termRepository, $redirectsByOrigin, $urlGenerator, new CmsSectionSlugs($termRepository)), new \Cms\Shared\Infrastructure\Http\AvatarUrl($urlGenerator, new SignedUrls(new UriSigner('secret'))));

        return new PreviewTerm(
            new PreviewLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, $redirectsByOrigin, $linkedContents, self::createStub(PageRepository::class)),
            new SiteTermPage(new PostCardsComponent($translator), $translator),
            new TermAddress($urlGenerator),
            $urlGenerator,
            new CmsPostCards($postRepository, $imageRepository, $termRepository, $redirectsByOrigin, $urlGenerator, new CmsSectionSlugs($termRepository)),
        );
    }
}
