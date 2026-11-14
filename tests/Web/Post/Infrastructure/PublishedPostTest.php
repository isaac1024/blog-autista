<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page as SitePage;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SitePostPage;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostResponse;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Infrastructure\Controller\PublishedPost;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

final class PublishedPostTest extends TestCase
{
    private const string SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    private const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    private const string OTHER_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a03';

    private const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    public function testItLeadsToTheSectionTheAuthorAndTheTagsOnTheSiteByTheirNamesThere(): void
    {
        $page = $this->compose(
            [self::TAG, self::OTHER_TAG],
            terms: [
                self::SECTION => self::term('section', 'Día a día', 'dia-a-dia'),
                self::OTHER_TAG => self::term('tag', 'Ruido', 'ruido'),
            ],
            authors: [self::AUTHOR => self::author('Isaac', 'isaac')],
        );

        PageComponents::one($page, 'Shared:PostMeta', [
            'section' => ['name' => 'Día a día', 'url' => '/dia-a-dia/'],
            'author' => ['name' => 'Isaac', 'url' => '/autor/isaac.html'],
        ]);
        // Only the tags on the site, in order.
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Ruido', 'url' => '/tag/ruido.html']]]);
        self::assertSame(RouteNameUrlGenerator::SITE.'/vida-diaria/2026/11/07/mi-primer-dia.html', $page->canonical);
        self::assertSame('index,follow,max-image-preview:large', $page->robots);
    }

    public function testItNamesTheSectionAndTheAuthorOffTheSiteAsTheyWereWhenPublishedWithoutLinks(): void
    {
        $page = $this->compose([self::TAG], terms: [self::SECTION => self::term('tag', 'Vida diaria', 'vida-diaria')]);

        PageComponents::one($page, 'Shared:PostMeta', [
            'section' => ['name' => 'Vida diaria', 'url' => null],
            'author' => ['name' => 'Isaac Abreu', 'url' => null],
        ]);
        self::assertSame([], PageComponents::find($page, 'Shared:PostTags', []));
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('dates')]
    public function testItDatesThePostByItsLastPublicationAndSaysWhetherItWasUpdated(array $changes, bool $updated, string $date): void
    {
        PageComponents::one($this->compose([], changes: $changes), 'Shared:PostMeta', ['updated' => $updated, 'date' => $date]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool, string}>
     */
    public static function dates(): iterable
    {
        yield 'published once' => [[], false, '7 de noviembre de 2026'];
        yield 'published again the same day' => [['last_published_at' => ClockMother::now('2026-11-07 21:00:00 UTC')->format(\DATE_ATOM)], false, '7 de noviembre de 2026'];
        yield 'published again on the next day of Madrid' => [['last_published_at' => ClockMother::now('2026-11-07 23:30:00 UTC')->format(\DATE_ATOM)], true, '8 de noviembre de 2026'];
        yield 'published again days later' => [['last_published_at' => ClockMother::now('2026-11-20 10:00:00')->format(\DATE_ATOM)], true, '20 de noviembre de 2026'];
    }

    /**
     * @param list<string>          $tags
     * @param array<string, Term>   $terms   by id
     * @param array<string, Author> $authors by id
     * @param array<string, mixed>  $changes
     */
    private function compose(array $tags, array $terms = [], array $authors = [], array $changes = []): SitePage
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => $terms[$id->value()] ?? null);
        $authorRepository = self::createStub(AuthorRepository::class);
        $authorRepository->method('find')->willReturnCallback(static fn (AuthorId $id): ?Author => $authors[$id->value()] ?? null);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirects = new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class));

        $post = Post::publish(CmsPostPublished::fromPrimitives(
            CmsPostSubscribersTest::POST,
            $changes + CmsPostSubscribersTest::body(tagIds: $tags) + ['revision' => 1],
        ));

        $page = new PublishedPost(
            $urlGenerator,
            new SitePostPage(new RichTextComponents(), $translator),
            new SiteLayout($urlGenerator, self::createStub(SettingsRepository::class), $pageRepository, $imageRepository, $redirects),
            $imageRepository,
            $pageRepository,
            $termRepository,
            $authorRepository,
            $redirects,
        )(PostResponse::fromPost($post));
        ComponentContracts::assertHonoured($page);

        return $page;
    }

    private static function term(string $taxonomy, string $name, string $slug): Term
    {
        return Term::publish(CmsTermPublished::fromPrimitives(TermId::random()->value(), [
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'meta_description' => 'Sobre esto.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'revision' => 1,
        ]));
    }

    private static function author(string $name, string $slug): Author
    {
        return Author::publish(CmsAuthorPublished::fromPrimitives(AuthorId::random()->value(), [
            'name' => $name,
            'slug' => $slug,
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'revision' => 1,
        ]));
    }
}
