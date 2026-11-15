<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Resolution;

use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Shared\Domain\RichText\RichText;
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
        $posts->save(PostMother::published(id: self::POST, title: 'Editado', publishedTitle: 'En la web'));
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
            self::POST => ['url' => '/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html', 'title' => 'En la web'],
            self::OFF_SITE_TAG => ['url' => '/tag/sueno.html', 'title' => 'Sueño'],
            self::AUTHOR => ['url' => '/autor/isaac.html', 'title' => 'Isaac'],
            self::SECTION => ['url' => '/vida-diaria/', 'title' => 'Vida diaria'],
        ], self::getContainer()->get(CmsLinkedContents::class)($content, [], null));
    }
}
