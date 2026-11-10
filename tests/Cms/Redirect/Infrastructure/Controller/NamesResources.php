<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names the resources of redirects after the pages and images a test keeps,
 * as the CMS would find them by their ids.
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

        return new RedirectNames($pageRepository, $imageRepository, $translator);
    }
}
