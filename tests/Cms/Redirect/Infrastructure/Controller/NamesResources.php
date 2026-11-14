<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Infrastructure\Controller\LastAddressPanel;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use Cms\Redirect\Infrastructure\Controller\ReplacedRedirectNotice;
use Cms\Taxonomy\Domain\TermRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names the resources of redirects after the pages and images a test keeps,
 * as the CMS would find them by their ids, and tells the editor of a
 * resource on the site, which has no last address to show.
 *
 * @phpstan-require-extends TestCase
 */
trait NamesResources
{
    /**
     * @param list<Page>  $pages
     * @param list<Image> $images
     */
    private function redirectNames(TranslatorInterface $translator, array $pages = [], array $images = []): RedirectNames
    {
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn($pages);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn($images);

        return new RedirectNames(
            $pageRepository,
            $imageRepository,
            self::createStub(PostRepository::class),
            self::createStub(TermRepository::class),
            self::createStub(AuthorRepository::class),
            $translator,
        );
    }

    /**
     * What the editor of a resource on the site shows of its last address:
     * nothing, since it has its address there.
     */
    private function lastAddressPanelOnSite(TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator): LastAddressPanel
    {
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('isOnSite')->willReturn(true);
        $redirectNames = $this->redirectNames($translator);

        return new LastAddressPanel(
            self::createStub(RedirectRepository::class),
            $siteResources,
            $redirectNames,
            $translator,
            $urlGenerator,
            new ReplacedRedirectNotice($redirectNames, $translator),
        );
    }
}
