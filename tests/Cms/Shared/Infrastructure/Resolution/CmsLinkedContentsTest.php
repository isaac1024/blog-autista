<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Resolution;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What a rich text previewed links to, as the site would show it once
 * published: each kind as it is on the site, by its name there; one off it,
 * as what its last address now leads to; a draft, nothing.
 */
final class CmsLinkedContentsTest extends KernelTestCase
{
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105b001';
    private const string POST = '01a1b801-ce58-723d-8234-b76d9105b002';
    private const string DRAFT_POST = '01a1b801-ce58-723d-8234-b76d9105b003';
    private const string SECTION = '01a1b801-ce58-723d-8234-b76d9105b011';
    private const string TAG = '01a1b801-ce58-723d-8234-b76d9105b012';
    private const string OFF_SITE_TAG = '01a1b801-ce58-723d-8234-b76d9105b013';
    private const string AUTHOR = '01a1b801-ce58-723d-8234-b76d9105b021';

    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $posts = self::getContainer()->get(PostRepository::class);
        $posts->save(PostMother::published(id: self::POST, title: 'Editado', section: self::SECTION, publishedTitle: 'En la web'));
        self::getContainer()->get(ImageRepository::class)->save(ImageMother::published(id: PostMother::OPENING_IMAGE));
        $posts->save(PostMother::create(id: self::DRAFT_POST, slug: 'borrador'));
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(TermMother::published(id: self::SECTION, name: 'Editada', publishedName: 'Vida diaria'));
        $terms->save(TermMother::published(id: self::TAG, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));
        $terms->save(TermMother::unpublished(id: self::OFF_SITE_TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido'));
        self::getContainer()->get(AuthorRepository::class)->save(AuthorMother::published(id: self::AUTHOR, name: 'Isaac', slug: 'isaac'));
        self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::create(
            id: RedirectIdMother::random()->value(),
            source: '/tag/ruido.html',
            target: Resource::term(self::TAG),
            origin: Resource::term(self::OFF_SITE_TAG),
        ));
    }

    public function testItFindsEveryKindOfContentLinkedAsTheSiteWouldServeIt(): void
    {
        $content = RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'paragraph', 'text' => array_map(static fn (array $link): array => ['text' => 'enlace', 'marks' => ['link'], ...$link], [
                ['page_id' => self::PAGE],
                ['post_id' => self::POST],
                ['post_id' => self::DRAFT_POST],
                ['term_id' => self::OFF_SITE_TAG],
                ['author_id' => self::AUTHOR],
            ])],
            ['type' => 'widget', 'kind' => 'related_page', 'term_id' => self::SECTION],
        ]]);

        self::assertEqualsCanonicalizing([
            self::PAGE => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí'],
            self::POST => ['url' => 'http://www.blogautista.localhost/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html', 'title' => 'En la web'],
            self::OFF_SITE_TAG => ['url' => '/tag/sueno.html', 'title' => 'Sueño'],
            self::AUTHOR => ['url' => '/autor/isaac.html', 'title' => 'Isaac'],
            self::SECTION => ['url' => '/vida-diaria/', 'title' => 'Vida diaria'],
        ], array_map(
            static fn (array $linked): array => ['url' => $linked['url'], 'title' => $linked['title']],
            self::getContainer()->get(CmsLinkedContents::class)($content, [], null),
        ));
    }

    /**
     * A card of related content shows, as the site does, what each kind says
     * of itself to search engines; a post, its section, the day it was first
     * published and its opening image too; an author, their avatar, by a
     * link of the CMS while it lasts.
     */
    public function testItTellsWhatACardOfRelatedContentShowsOfEachKind(): void
    {
        $content = RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'paragraph', 'text' => array_map(static fn (array $link): array => ['text' => 'enlace', 'marks' => ['link'], ...$link], [
                ['page_id' => self::PAGE], ['post_id' => self::POST], ['term_id' => self::SECTION], ['term_id' => self::TAG], ['author_id' => self::AUTHOR],
            ])],
        ]]);

        $related = array_map(
            static fn (array $linked): mixed => $linked['related'] ?? null,
            self::getContainer()->get(CmsLinkedContents::class)($content, [], null),
        );

        $avatar = $related[self::AUTHOR]['image'] ?? null;
        self::assertIsArray($avatar);
        self::assertStringStartsWith('http://cms.blogautista.localhost/preview/authors/'.self::AUTHOR.'/avatar/'.AuthorMother::AVATAR.'?_expiration=', $avatar['src']);
        $nothing = ['image' => null, 'section' => null, 'published' => null];
        self::assertEquals([
            self::PAGE => ['kind' => 'page', 'summary' => PageMother::META_DESCRIPTION, ...$nothing],
            self::POST => [
                'kind' => 'post',
                'summary' => PostMother::META_DESCRIPTION,
                'image' => ['src' => '/media/2026/11/'.PostMother::OPENING_IMAGE.'-1200w.webp', 'srcset' => \sprintf('/media/2026/11/%1$s-400w.webp 400w, /media/2026/11/%1$s-800w.webp 800w, /media/2026/11/%1$s-1200w.webp 1200w', PostMother::OPENING_IMAGE), 'width' => 1200, 'height' => 675],
                'section' => 'Vida diaria',
                'published' => ClockMother::now(),
            ],
            self::SECTION => ['kind' => 'section', 'summary' => TermMother::META_DESCRIPTION, ...$nothing],
            self::TAG => ['kind' => 'tag', 'summary' => TermMother::META_DESCRIPTION, ...$nothing],
            self::AUTHOR => ['kind' => 'author', 'summary' => AuthorMother::META_DESCRIPTION, 'image' => ['src' => $avatar['src'], 'srcset' => '', 'width' => 400, 'height' => 400], 'section' => null, 'published' => null],
        ], $related);
    }

    /**
     * A post whose section is off the site, or is a tag, shows no section on
     * its card.
     */
    public function testACardOfAPostShowsNoSectionThatIsNotOneOnTheSite(): void
    {
        $posts = self::getContainer()->get(PostRepository::class);
        $posts->save(PostMother::published(id: '01a1b801-ce58-723d-8234-b76d9105b004', slug: 'sin-seccion', section: '01a1b801-ce58-723d-8234-b76d9105b0ff'));
        $posts->save(PostMother::published(id: '01a1b801-ce58-723d-8234-b76d9105b005', slug: 'con-etiqueta', section: self::TAG));
        $content = RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'paragraph', 'text' => [
                ['text' => 'uno', 'marks' => ['link'], 'post_id' => '01a1b801-ce58-723d-8234-b76d9105b004'],
                ['text' => 'otro', 'marks' => ['link'], 'post_id' => '01a1b801-ce58-723d-8234-b76d9105b005'],
            ]],
        ]]);

        self::assertSame([null, null], array_values(array_map(
            static fn (array $linked): mixed => \is_array($linked['related'] ?? null) && \array_key_exists('section', $linked['related']) ? $linked['related']['section'] : 'no card',
            self::getContainer()->get(CmsLinkedContents::class)($content, [], null),
        )));
    }
}
