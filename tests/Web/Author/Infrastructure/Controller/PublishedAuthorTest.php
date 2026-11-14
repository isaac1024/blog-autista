<?php

declare(strict_types=1);

namespace Web\Tests\Author\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SiteAuthorPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Web\Author\Application\AuthorResponse;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Author\Infrastructure\Controller\PublishedAuthor;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\PageRepository;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Domain\TermRepository;

final class PublishedAuthorTest extends TestCase
{
    private const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    /**
     * The page of an author counts its visits as those of the author.
     */
    public function testItCountsTheVisitsOfTheAuthor(): void
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirects = new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class));
        $author = Author::publish(CmsAuthorPublished::fromPrimitives(self::AUTHOR, [
            'name' => 'Isaac',
            'slug' => 'isaac',
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ]));

        $page = new PublishedAuthor(
            $urlGenerator,
            new SiteAuthorPage(new RichTextComponents()),
            new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $pageRepository, $imageRepository, $redirects),
            $imageRepository,
            $redirects,
            new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects),
        )(AuthorResponse::fromAuthor($author));
        ComponentContracts::assertHonoured($page);

        self::assertSame(['type' => 'author', 'id' => self::AUTHOR], $page->countedContent);
    }
}
