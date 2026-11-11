<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Infrastructure\Page;

use Cms\Analytics\Domain\PageTitles;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The pages are named by the titles the CMS keeps; a page deleted, or what
 * is no id of one, has no name.
 */
final class CmsPageTitlesTest extends KernelTestCase
{
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105f001';
    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105f002';
    private const string DELETED_PAGE = '01a1b801-ce58-723d-8234-b76d9105f003';

    public function testItNamesThePagesThatStillExist(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::published(id: self::OTHER_PAGE, title: 'Otra página', slug: 'otra-pagina'));

        self::assertEqualsCanonicalizing(
            [self::PAGE => 'Sobre mí', self::OTHER_PAGE => 'Otra página'],
            self::getContainer()->get(PageTitles::class)->titlesOf([self::PAGE, self::OTHER_PAGE, self::DELETED_PAGE, 'sobre-mi']),
        );
    }
}
