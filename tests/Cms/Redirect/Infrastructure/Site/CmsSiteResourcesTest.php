<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Site;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\InMemoryPostImages;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * What the site has, as the pages, the images and the routes of the CMS
 * tell it. The CMS holds a page on the site at /sobre-mi.html, saved to move
 * to /quien-soy.html; one off it, last at /retirada.html; a draft at
 * /borrador.html; the home page; an image on the site, one off it and a
 * draft. It holds a section on the site at /vida-diaria/, saved to move to
 * /dia-a-dia/, a tag off it, last at /tag/sueno.html, and a draft tag; an
 * author on the site at /autor/isaac.html and one off it, last at
 * /autor/ana.html; a post on the site under the section, first published on
 * 7 November 2026, saved to move to another slug; one off it, last at the
 * address of its own slug that day; and a draft under the section, which
 * would be published today.
 */
final class CmsSiteResourcesTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string DRAFT = '0196f3a0-0000-7000-8000-0000000000a3';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000a4';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string IMAGE_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000c2';
    private const string IMAGE_DRAFT = '0196f3a0-0000-7000-8000-0000000000c3';
    private const string NONE = '0196f3a0-0000-7000-8000-0000000000ff';
    private const string SECTION = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string TAG_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000d2';
    private const string TAG_DRAFT = '0196f3a0-0000-7000-8000-0000000000d3';
    private const string AUTHOR = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string AUTHOR_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string POST = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string POST_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string POST_DRAFT = '0196f3a0-0000-7000-8000-0000000000b3';

    /**
     * Where the post on the site is, and where the one off it last was.
     */
    private const string POST_PATH = '/vida-diaria/2026/11/07/otro-dia.html';
    private const string POST_OFF_SITE_PATH = '/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html';

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
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(TermMother::published(id: self::SECTION, slug: 'dia-a-dia', publishedSlug: 'vida-diaria'));
        $terms->save(TermMother::unpublished(id: self::TAG_OFF_SITE, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));
        $terms->save(TermMother::create(id: self::TAG_DRAFT, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido'));
        $authors = self::getContainer()->get(AuthorRepository::class);
        $authors->save(AuthorMother::published(id: self::AUTHOR));
        $authors->save(AuthorMother::unpublished(id: self::AUTHOR_OFF_SITE, slug: 'ana'));
        $posts = self::getContainer()->get(PostRepository::class);
        $post = PostMother::published(id: self::POST, slug: 'otro-dia', section: self::SECTION, author: self::AUTHOR);
        $post->edit(PostMother::TITLE, 'otro-dia-mas', self::SECTION, self::AUTHOR, [], PostMother::OPENING_IMAGE, PostMother::CONTENT, PostMother::META_DESCRIPTION, 'index,follow', self::getContainer()->get(Classifiers::class), new InMemoryPostImages());
        $posts->save($post);
        $posts->save(PostMother::unpublished(id: self::POST_OFF_SITE));
        $posts->save(PostMother::publishable(id: self::POST_DRAFT, slug: 'borrador', section: self::SECTION, author: self::AUTHOR));
        self::mockTime(ClockMother::now('2026-11-14 10:00:00'));
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
        yield 'a post on the site' => [Resource::post(self::POST), true];
        yield 'a post off it' => [Resource::post(self::POST_OFF_SITE), false];
        yield 'a post draft' => [Resource::post(self::POST_DRAFT), false];
        yield 'no post' => [Resource::post(self::NONE), false];
        yield 'a section on the site' => [Resource::term(self::SECTION), true];
        yield 'a tag off it' => [Resource::term(self::TAG_OFF_SITE), false];
        yield 'no term' => [Resource::term(self::NONE), false];
        yield 'an author on the site' => [Resource::author(self::AUTHOR), true];
        yield 'an author off it' => [Resource::author(self::AUTHOR_OFF_SITE), false];
        yield 'no author' => [Resource::author(self::NONE), false];
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
        // A browser asking for it gets no "not found" a redirect could take.
        yield 'an address only sent to' => [Source::fromPath('/v'), true];
        yield 'an address no route answers' => [Source::fromPath('/blog/vieja'), false];
        yield 'a section on the site' => [Source::fromPath('/vida-diaria/'), true];
        yield 'the address a section will move to' => [Source::fromPath('/dia-a-dia/'), false];
        yield 'a section there is none of' => [Source::fromPath('/otra-seccion/'), false];
        // The router sends these to the address with, or without, the slash.
        yield 'a section on the site, without its final slash' => [Source::fromPath('/vida-diaria'), true];
        yield 'any address a section could have, without its final slash' => [Source::fromPath('/otra-seccion'), true];
        yield 'a page on the site, with a final slash' => [Source::fromPath('/sobre-mi.html/'), true];
        yield 'a post on the site, with a final slash' => [Source::fromPath(self::POST_PATH.'/'), true];
        yield 'the last address of a tag off the site' => [Source::fromPath('/tag/sueno.html'), false];
        yield 'a tag at the slug of a section' => [Source::fromPath('/tag/vida-diaria.html'), false];
        yield 'an author on the site' => [Source::fromPath('/autor/isaac.html'), true];
        yield 'the last address of an author off the site' => [Source::fromPath('/autor/ana.html'), false];
        yield 'a post on the site' => [Source::fromPath(self::POST_PATH), true];
        yield 'the last address of a post off the site' => [Source::fromPath(self::POST_OFF_SITE_PATH), false];
        yield 'a post on another day' => [Source::fromPath('/vida-diaria/2026/11/08/otro-dia.html'), false];
        yield 'a post on a day the calendar lacks' => [Source::fromPath('/vida-diaria/2026/02/30/otro-dia.html'), false];
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
        // The address of a post is kept without its section, which only says where it is filed.
        yield 'a post on the site, saved to move' => [Resource::post(self::POST), '/2026/11/07/otro-dia.html', '/2026/11/07/otro-dia-mas.html'];
        yield 'a post off the site' => [Resource::post(self::POST_OFF_SITE), '/2026/11/07/mi-primer-dia-sin-ruido.html', null];
        yield 'a post draft, which would take today' => [Resource::post(self::POST_DRAFT), null, '/2026/11/14/borrador.html'];
        yield 'no post' => [Resource::post(self::NONE), null, null];
        yield 'a section on the site, saved to move' => [Resource::term(self::SECTION), '/vida-diaria/', '/dia-a-dia/'];
        yield 'a tag off the site' => [Resource::term(self::TAG_OFF_SITE), '/tag/sueno.html', '/tag/sueno.html'];
        yield 'a tag draft' => [Resource::term(self::TAG_DRAFT), null, '/tag/ruido.html'];
        yield 'no term' => [Resource::term(self::NONE), null, null];
        yield 'an author on the site' => [Resource::author(self::AUTHOR), '/autor/isaac.html', '/autor/isaac.html'];
        yield 'an author off the site' => [Resource::author(self::AUTHOR_OFF_SITE), '/autor/ana.html', '/autor/ana.html'];
        yield 'no author' => [Resource::author(self::NONE), null, null];
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
        yield 'the home page' => [Source::fromPath('/'), null];
        yield 'the last address of a tag off the site' => [Source::fromPath('/tag/sueno.html'), Resource::term(self::TAG_OFF_SITE)];
        yield 'a section on the site' => [Source::fromPath('/vida-diaria/'), null];
        yield 'the last address of an author off the site' => [Source::fromPath('/autor/ana.html'), Resource::author(self::AUTHOR_OFF_SITE)];
        yield 'an author on the site' => [Source::fromPath('/autor/isaac.html'), null];
        yield 'the last address of a post off the site' => [Source::fromPath(self::POST_OFF_SITE_PATH), Resource::post(self::POST_OFF_SITE)];
        yield 'a post on the site' => [Source::fromPath(self::POST_PATH), null];
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
