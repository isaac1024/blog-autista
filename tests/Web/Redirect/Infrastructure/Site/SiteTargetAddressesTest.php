<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Site;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Redirect\Domain\TargetAddresses;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

/**
 * Where the site serves each resource now: a page at its slug, the home
 * page at the root, and an image at the copy of the width asked for. The
 * site has a page, the home page and an image.
 */
final class SiteTargetAddressesTest extends KernelTestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string NONE = '0196f3a0-0000-7000-8000-0000000000ff';

    #[\Override]
    protected function setUp(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE, title: 'Destino', slug: 'destino')));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home)));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/'.self::IMAGE.'.webp')));
    }

    /**
     * @return iterable<string, array{string, string, ?int, ?string}>
     */
    public static function resources(): iterable
    {
        yield 'a page' => ['page', self::PAGE, null, '/destino.html'];
        yield 'the home page' => ['page', self::HOME, null, '/'];
        yield 'a page not on the site' => ['page', self::NONE, null, null];
        yield 'an image at a width' => ['image', self::IMAGE, 400, '/media/2026/11/'.self::IMAGE.'-400w.webp'];
        yield 'an image at no width' => ['image', self::IMAGE, null, '/media/2026/11/'.self::IMAGE.'-1200w.webp'];
        yield 'an image not on the site' => ['image', self::NONE, 400, null];
        yield 'no kind of resource the site has' => ['video', self::PAGE, null, null];
    }

    #[DataProvider('resources')]
    public function testItTellsWhereTheSiteServesAResourceNow(string $type, string $id, ?int $width, ?string $address): void
    {
        self::assertSame($address, self::getContainer()->get(TargetAddresses::class)->addressOf($type, $id, $width));
    }
}
