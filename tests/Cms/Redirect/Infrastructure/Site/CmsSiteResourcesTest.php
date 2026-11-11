<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Site;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the site has, as the pages, the images and the routes of the CMS
 * tell it. The CMS holds a page on the site at /sobre-mi.html, saved to move
 * to /quien-soy.html; one off it, last at /retirada.html; a draft at
 * /borrador.html; the home page; an image on the site, one off it and a
 * draft.
 */
final class CmsSiteResourcesTest extends KernelTestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string DRAFT = '0196f3a0-0000-7000-8000-0000000000a3';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000a4';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string IMAGE_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000c2';
    private const string IMAGE_DRAFT = '0196f3a0-0000-7000-8000-0000000000c3';
    private const string NONE = '0196f3a0-0000-7000-8000-0000000000ff';

    #[\Override]
    protected function setUp(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $page = PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');
        $pages->save($page);
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        $pages->save(PageMother::create(id: self::DRAFT, title: 'Borrador', slug: 'borrador'));
        $pages->save(PageMother::home(id: self::HOME));
        $images = self::getContainer()->get(ImageRepository::class);
        $images->save(ImageMother::published(id: self::IMAGE));
        $images->save(ImageMother::unpublished(id: self::IMAGE_OFF_SITE));
        $images->save(ImageMother::create(id: self::IMAGE_DRAFT));
    }

    /**
     * @return iterable<string, array{resource, bool}>
     */
    public static function resources(): iterable
    {
        yield 'a page on the site' => [Resource::page(self::PAGE), true];
        yield 'a page off it' => [Resource::page(self::OFF_SITE), false];
        yield 'a page draft' => [Resource::page(self::DRAFT), false];
        yield 'no page' => [Resource::page(self::NONE), false];
        yield 'an image on the site' => [Resource::image(self::IMAGE), true];
        yield 'an image off it' => [Resource::image(self::IMAGE_OFF_SITE), false];
        yield 'no image' => [Resource::image(self::NONE), false];
    }

    #[DataProvider('resources')]
    public function testItTellsWhetherAResourceIsOnTheSite(Resource $resource, bool $onSite): void
    {
        self::assertSame($onSite, $this->site()->isOnSite($resource));
    }

    /**
     * @return iterable<string, array{Source, bool}>
     */
    public static function addresses(): iterable
    {
        yield 'a page on the site' => [Source::fromPath('/sobre-mi.html'), true];
        yield 'the address a page will move to' => [Source::fromPath('/quien-soy.html'), false];
        yield 'the last address of a page off the site' => [Source::fromPath('/retirada.html'), false];
        yield 'the home page' => [Source::fromPath('/'), true];
        yield 'an address only sent to' => [Source::fromPath('/v'), false];
        yield 'an address no route answers' => [Source::fromPath('/blog/vieja'), false];
        yield 'an image on the site' => [self::image(self::IMAGE), true];
        yield 'an image off it' => [self::image(self::IMAGE_OFF_SITE), false];
        yield 'an image there is none of' => [self::image(self::NONE), false];
    }

    #[DataProvider('addresses')]
    public function testItTellsWhetherTheSiteAnswersAnAddressItself(Source $source, bool $live): void
    {
        self::assertSame($live, $this->site()->isLive($source));
    }

    /**
     * @return iterable<string, array{resource, ?string, ?string}>
     */
    public static function addressesOfResources(): iterable
    {
        $image = self::image(self::IMAGE)->value();

        yield 'a page on the site, saved to move' => [Resource::page(self::PAGE), '/sobre-mi.html', '/quien-soy.html'];
        yield 'a page off the site' => [Resource::page(self::OFF_SITE), '/retirada.html', '/retirada.html'];
        yield 'a page draft' => [Resource::page(self::DRAFT), null, '/borrador.html'];
        yield 'the home page, at no address a redirect answers' => [Resource::page(self::HOME), null, null];
        yield 'no page' => [Resource::page(self::NONE), null, null];
        yield 'an image, whose address never changes' => [Resource::image(self::IMAGE), $image, $image];
        yield 'an image draft, never on the site' => [Resource::image(self::IMAGE_DRAFT), null, self::image(self::IMAGE_DRAFT)->value()];
        yield 'no image' => [Resource::image(self::NONE), null, null];
    }

    #[DataProvider('addressesOfResources')]
    public function testItTellsTheAddressAResourceHasAndTheOneItWillHave(Resource $resource, ?string $current, ?string $next): void
    {
        self::assertSame([$current, $next], [$this->site()->addressOf($resource)?->value(), $this->site()->nextAddressOf($resource)?->value()]);
    }

    /**
     * @return iterable<string, array{Source, ?resource}>
     */
    public static function lastAddresses(): iterable
    {
        yield 'the last address of a page off the site' => [Source::fromPath('/retirada.html'), Resource::page(self::OFF_SITE)];
        yield 'the address of a page on the site' => [Source::fromPath('/sobre-mi.html'), null];
        yield 'an address no page had' => [Source::fromPath('/vieja.html'), null];
        yield 'an image off the site' => [self::image(self::IMAGE_OFF_SITE), Resource::image(self::IMAGE_OFF_SITE)];
        yield 'an image on the site' => [self::image(self::IMAGE), null];
        yield 'an image there is none of' => [self::image(self::NONE), null];
        yield 'an address no route answers' => [Source::fromPath('/blog/vieja'), null];
    }

    #[DataProvider('lastAddresses')]
    public function testItTellsTheResourceOffTheSiteLastAtAnAddress(Source $source, ?Resource $last): void
    {
        self::assertEquals($last, $this->site()->lastAt($source));
    }

    /**
     * The address every copy of an image shares.
     */
    private static function image(string $id): Source
    {
        return Source::ofImage(ImageMother::path($id)->value());
    }

    private function site(): SiteResources
    {
        return self::getContainer()->get(SiteResources::class);
    }
}
