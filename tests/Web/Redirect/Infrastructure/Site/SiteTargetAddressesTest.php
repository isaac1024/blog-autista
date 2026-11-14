<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Site;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Domain\TargetAddresses;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Post\Infrastructure\CmsPostSubscribersTest;

/**
 * Where the site serves each resource now: a page at its slug, the home
 * page at the root, an image at the copy of the width asked for, a post
 * under its section on its day, a section, a tag and an author at their
 * slugs. The site has one of each.
 */
final class SiteTargetAddressesTest extends KernelTestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string NONE = '0196f3a0-0000-7000-8000-0000000000ff';
    private const string SECTION = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string TAG = '0196f3a0-0000-7000-8000-0000000000d2';
    private const string AUTHOR = '0196f3a0-0000-7000-8000-0000000000e1';

    #[\Override]
    protected function setUp(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE, title: 'Destino', slug: 'destino')));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home)));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/'.self::IMAGE.'.webp')));
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(CmsPostSubscribersTest::POST, CmsPostSubscribersTest::body() + ['revision' => 1])));
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(self::term(self::SECTION, 'section', 'vida-diaria'));
        $terms->save(self::term(self::TAG, 'tag', 'sueno'));
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublished::fromPrimitives(self::AUTHOR, [
            'name' => 'Isaac',
            'slug' => 'isaac',
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ])));
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
        yield 'a post' => ['post', CmsPostSubscribersTest::POST, null, '/vida-diaria/2026/11/07/mi-primer-dia.html'];
        yield 'a post not on the site' => ['post', self::NONE, null, null];
        yield 'a section' => ['term', self::SECTION, null, '/vida-diaria/'];
        yield 'a tag' => ['term', self::TAG, null, '/tag/sueno.html'];
        yield 'a term not on the site' => ['term', self::NONE, null, null];
        yield 'an author' => ['author', self::AUTHOR, null, '/autor/isaac.html'];
        yield 'an author not on the site' => ['author', self::NONE, null, null];
        yield 'no kind of resource the site has' => ['video', self::PAGE, null, null];
    }

    #[DataProvider('resources')]
    public function testItTellsWhereTheSiteServesAResourceNow(string $type, string $id, ?int $width, ?string $address): void
    {
        self::assertSame($address, self::getContainer()->get(TargetAddresses::class)->addressOf($type, $id, $width));
    }

    private static function term(string $id, string $taxonomy, string $slug): Term
    {
        return Term::publish(CmsTermPublished::fromPrimitives($id, [
            'taxonomy' => $taxonomy,
            'name' => 'Término',
            'slug' => $slug,
            'meta_description' => 'Sobre esto.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ]));
    }
}
