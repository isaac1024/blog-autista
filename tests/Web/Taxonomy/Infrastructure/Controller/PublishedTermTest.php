<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\SiteTermPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Web\Author\Domain\AuthorRepository;
use Web\External\Domain\ExternalResourceRepository as WebExternalResourceRepository;
use Web\External\Infrastructure\Ui\SiteExternalViews;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\PageRepository;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Taxonomy\Infrastructure\Controller\PublishedTerm;

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

        $page = new PublishedTerm(
            new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, $redirects, new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects, new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator))),
            new SiteTermPage(),
        )(TermResponse::fromTerm($term), RouteNameUrlGenerator::SITE.'/tag/sueno.html');
        ComponentContracts::assertHonoured($page);

        self::assertSame(['type' => 'term', 'id' => self::TERM], $page->countedContent);
    }
}
