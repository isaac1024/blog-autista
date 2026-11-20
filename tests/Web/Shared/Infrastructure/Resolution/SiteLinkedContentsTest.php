<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Resolution;

use Doctrine\DBAL\Connection;
use Shared\Domain\RichText\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

/**
 * What a rich text painted on the site links to, its widgets and the
 * captions of its images too, each kind found where the site keeps it: a
 * page, a post, a section, a tag and an author on the site; one off it, as
 * what its last address now leads to, when that is of its kind, and never
 * the content painted.
 */
final class SiteLinkedContentsTest extends KernelTestCase
{
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105a001';
    private const string SECTION = '01a1b801-ce58-723d-8234-b76d9105a011';
    private const string TAG = '01a1b801-ce58-723d-8234-b76d9105a012';
    private const string AUTHOR = '01a1b801-ce58-723d-8234-b76d9105a021';
    private const string GONE_TAG = '01a1b801-ce58-723d-8234-b76d9105a013';
    private const string GONE_AUTHOR = '01a1b801-ce58-723d-8234-b76d9105a022';
    private const string OFF_SITE = '01a1b801-ce58-723d-8234-b76d9105a099';
    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105a031';

    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi')));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: CmsPostPublishedMother::OPENING_IMAGE, path: '2026/11/faro.webp')));
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::DEFAULT_VALUE, CmsPostPublishedMother::body() + ['revision' => 1])));
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(self::term(self::SECTION, 'section', 'Vida diaria', 'vida-diaria'));
        $terms->save(self::term(self::TAG, 'tag', 'Sueño', 'sueno'));
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
        // A tag off the site whose address leads to the tag on it, and an
        // author off it whose address leads to a page: not one of its kind.
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c001', '/tag/ruido.html', 'term', self::TAG, 'term', self::GONE_TAG);
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c002', '/autor/ana.html', 'page', self::PAGE, 'author', self::GONE_AUTHOR);
    }

    /**
     * A card of related content shows what each kind says of itself to
     * search engines; a post, its section, the day it was first published
     * and its opening image too; an author, their avatar.
     */
    public function testItTellsWhatACardOfRelatedContentShowsOfEachKind(): void
    {
        $content = self::text(['page_id' => self::PAGE], ['post_id' => PostIdMother::DEFAULT_VALUE], ['term_id' => self::SECTION], ['term_id' => self::TAG], ['author_id' => self::AUTHOR]);

        $related = array_map(
            static fn (array $linked): mixed => $linked['related'] ?? null,
            self::getContainer()->get(SiteLinkedContents::class)($content, [], null),
        );

        $nothing = ['image' => null, 'section' => null, 'published' => null];
        self::assertEquals([
            self::PAGE => ['kind' => 'page', 'summary' => 'Quién escribe este blog sobre autismo y por qué.', ...$nothing],
            PostIdMother::DEFAULT_VALUE => [
                'kind' => 'post',
                'summary' => 'Qué cambió el primer día con tapones.',
                'image' => ['src' => '/media/2026/11/faro-1200w.webp', 'srcset' => '/media/2026/11/faro-400w.webp 400w, /media/2026/11/faro-800w.webp 800w, /media/2026/11/faro-1200w.webp 1200w', 'width' => 1200, 'height' => 675],
                'section' => 'Vida diaria',
                'published' => new \DateTimeImmutable('2026-11-07T10:00:00+00:00'),
            ],
            self::SECTION => ['kind' => 'section', 'summary' => 'Sobre esto.', ...$nothing],
            self::TAG => ['kind' => 'tag', 'summary' => 'Sobre esto.', ...$nothing],
            self::AUTHOR => [
                'kind' => 'author',
                'summary' => 'Quién escribe.',
                'image' => ['src' => '/media/autores/isaac-68f1e2a357-400w.webp', 'srcset' => '/media/autores/isaac-68f1e2a357-96w.webp 96w, /media/autores/isaac-68f1e2a357-200w.webp 200w, /media/autores/isaac-68f1e2a357-400w.webp 400w', 'width' => 400, 'height' => 400],
                'section' => null,
                'published' => null,
            ],
        ], $related);
    }

    /**
     * A post is summed up as everywhere else on the site: by the current
     * name of its section, and opening with the image its image's address
     * now leads to, when its own left the site.
     */
    public function testACardOfARelatedPostShowsItsSectionAndImageAsTheSiteHasThemNow(): void
    {
        self::getContainer()->get(TermRepository::class)->save(self::term(CmsPostPublishedMother::SECTION, 'section', 'Día a día', 'dia-a-dia'));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/bosque.webp')));
        self::getContainer()->get(Connection::class)->delete('web_image', ['id' => CmsPostPublishedMother::OPENING_IMAGE]);
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c004', '/media/2026/11/faro.webp', 'image', self::IMAGE, 'image', CmsPostPublishedMother::OPENING_IMAGE);

        $related = self::getContainer()->get(SiteLinkedContents::class)(self::text(['post_id' => PostIdMother::DEFAULT_VALUE]), [], null)[PostIdMother::DEFAULT_VALUE]['related'] ?? null;

        self::assertSame(['Día a día', '/media/2026/11/bosque-1200w.webp'], [$related['section'] ?? null, $related['image']['src'] ?? null]);
    }

    public function testItFindsEveryKindOfContentLinkedWhereTheSiteServesIt(): void
    {
        $content = self::text(
            ['page_id' => self::PAGE],
            ['post_id' => PostIdMother::DEFAULT_VALUE],
            ['term_id' => self::SECTION],
            ['author_id' => self::AUTHOR],
            ['term_id' => self::GONE_TAG],
            ['author_id' => self::GONE_AUTHOR],
            ['post_id' => self::OFF_SITE],
        );
        $caption = self::text(['term_id' => self::TAG]);

        $linked = self::getContainer()->get(SiteLinkedContents::class)($content, ['faro' => ['caption' => $caption]], null);

        self::assertEqualsCanonicalizing([
            self::PAGE => ['url' => '/sobre-mi.html', 'title' => 'Sobre mí'],
            PostIdMother::DEFAULT_VALUE => ['url' => '/vida-diaria/2026/11/07/mi-primer-dia.html', 'title' => 'Mi primer día sin ruido'],
            self::SECTION => ['url' => '/vida-diaria/', 'title' => 'Vida diaria'],
            self::AUTHOR => ['url' => '/autor/isaac.html', 'title' => 'Isaac'],
            self::TAG => ['url' => '/tag/sueno.html', 'title' => 'Sueño'],
            self::GONE_TAG => ['url' => '/tag/sueno.html', 'title' => 'Sueño'],
        ], array_map(static fn (array $linked): array => ['url' => $linked['url'], 'title' => $linked['title']], $linked));
    }

    /**
     * A post off the site whose address now leads to the post painted is
     * not shown as it: the post doesn't link itself.
     */
    public function testItNeverShowsTheContentPaintedInPlaceOfAnother(): void
    {
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c003', '/vida-diaria/2026/11/01/antiguo.html', 'post', PostIdMother::DEFAULT_VALUE, 'post', self::OFF_SITE);
        $content = self::text(['post_id' => self::OFF_SITE], ['page_id' => self::PAGE]);

        self::assertSame([self::PAGE], array_keys(self::getContainer()->get(SiteLinkedContents::class)($content, [], PostIdMother::DEFAULT_VALUE)));
        self::assertSame([self::PAGE, self::OFF_SITE], array_keys(self::getContainer()->get(SiteLinkedContents::class)($content, [], null)));
    }

    /**
     * @param array<string, string> ...$links each the key and id of what a run links to
     */
    private static function text(array ...$links): RichText
    {
        return RichText::fromArray(['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => array_map(
            static fn (array $link): array => ['text' => 'enlace', 'marks' => ['link'], ...$link],
            $links,
        )]]]);
    }

    private static function term(string $id, string $taxonomy, string $name, string $slug): Term
    {
        return Term::publish(CmsTermPublished::fromPrimitives($id, [
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'meta_description' => 'Sobre esto.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ]));
    }

    private static function redirect(string $id, string $source, string $targetType, string $targetId, string $originType, string $originId): void
    {
        self::getContainer()->get(Connection::class)->insert('shared_redirect', [
            'id' => $id, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'origin_type' => $originType, 'origin_id' => $originId,
            'created_at' => '2026-11-09 09:00:00', 'updated_at' => '2026-11-09 10:00:00', 'version' => 1,
        ]);
    }
}
