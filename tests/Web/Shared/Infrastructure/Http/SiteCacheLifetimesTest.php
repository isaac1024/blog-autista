<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Taxonomy\Domain\SectionSlugs;

/**
 * The page cache in front of the site keeps each page as long as the page
 * tells it, since nothing purges it: what lists posts a few minutes, a post
 * an hour. Browsers ask again every time.
 */
final class SiteCacheLifetimesTest extends WebTestCase
{
    use SectionSlugs;

    private const string HOST = 'http://www.blogautista.localhost';

    #[DataProvider('pages')]
    public function testItTellsThePageCacheHowLongToKeepEachPage(string $path, string $cacheControl): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create()));
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Inicio', slug: null, type: PageType::Home)));
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublishedMother::create()));
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublishedMother::create()));

        $client->request('GET', self::HOST.$path);

        self::assertResponseIsSuccessful();
        self::assertSame($cacheControl, $client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        yield 'the home, which may show mosaics' => ['/', 'max-age=0, public, s-maxage=300'];
        yield 'a page, which may show mosaics' => ['/sobre-mi.html', 'max-age=0, public, s-maxage=300'];
        yield 'an author, whose bio may show mosaics' => ['/autor/isaac.html', 'max-age=0, public, s-maxage=300'];
        yield 'a post, as soon corrected or taken off as the lists' => ['/vida-diaria/2026/11/07/mi-primer-dia.html', 'max-age=0, public, s-maxage=300'];
    }

    /**
     * A page the site doesn't have keeps the lifetime of its error, a
     * minute, not that of the pages of its route.
     */
    public function testItLeavesErrorsToTheirOwnLifetimes(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();

        $client->request('GET', self::HOST.'/no-existe.html');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('60', $client->getResponse()->headers->getCacheControlDirective('s-maxage'));
    }
}
