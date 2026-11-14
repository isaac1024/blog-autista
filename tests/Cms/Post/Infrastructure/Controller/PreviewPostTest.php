<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\Post;
use Cms\Post\Infrastructure\Controller\PostUrl;
use Cms\Post\Infrastructure\Controller\PreviewPost;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SitePostPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PreviewPostTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string NEW_SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0b01';

    private const string NEW_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0b02';

    private const string NEW_AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b03';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
    }

    public function testItLeadsToTheSectionTheAuthorAndTheTagsOnTheSiteByTheirNamesThere(): void
    {
        $page = $this->preview(PostMother::publishable(tags: [PostMother::OTHER_TAG, PostMother::TAG]), onSite: true);

        PageComponents::one($page, 'Shared:PostMeta', [
            'section' => ['name' => 'Publicada', 'url' => '/vida-diaria/'],
            'author' => ['name' => 'Firma publicada', 'url' => '/autor/isaac.html'],
        ]);
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Sensorialidad', 'url' => '/tag/sensorialidad.html']]]);
        self::assertSame('noindex,nofollow', $page->robots);
    }

    public function testItNamesTheSectionAndTheAuthorOffTheSiteAsTheyAreSavedWithoutLinks(): void
    {
        $page = $this->preview(PostMother::publishable(tags: [PostMother::OTHER_TAG]), onSite: false);

        PageComponents::one($page, 'Shared:PostMeta', [
            'section' => ['name' => TermMother::NAME, 'url' => null],
            'author' => ['name' => AuthorMother::NAME, 'url' => null],
        ]);
        self::assertSame([], PageComponents::find($page, 'Shared:PostTags', []));
        self::assertSame(RouteNameUrlGenerator::SITE.'/', $page->canonical);
    }

    public function testItNamesNoSectionNorAuthorOfAnUnfiledPost(): void
    {
        PageComponents::one($this->preview(PostMother::create(), onSite: true), 'Shared:PostMeta', [
            'section' => ['name' => '', 'url' => null],
            'author' => ['name' => '', 'url' => null],
        ]);
    }

    /**
     * A section, an author or a tag off the site is previewed as the one its
     * last address now leads to, as the site would show it.
     */
    public function testItShowsWhatIsOffTheSiteAsWhatItsAddressNowLeadsTo(): void
    {
        $page = $this->preview(
            PostMother::publishable(tags: [PostMother::OTHER_TAG]),
            onSite: false,
            others: [
                self::NEW_SECTION => TermMother::published(id: self::NEW_SECTION, name: 'Día a día', slug: 'dia-a-dia'),
                self::NEW_TAG => TermMother::published(id: self::NEW_TAG, taxonomy: Taxonomy::Tag, name: 'Calma', slug: 'calma'),
            ],
            otherAuthor: AuthorMother::published(id: self::NEW_AUTHOR, name: 'Ana', slug: 'ana'),
            redirects: [
                RedirectMother::create(id: RedirectIdMother::random()->value(), source: '/vida-diaria/', target: Resource::term(self::NEW_SECTION), origin: Resource::term(PostMother::SECTION)),
                RedirectMother::create(id: RedirectIdMother::random()->value(), source: '/autor/isaac.html', target: Resource::author(self::NEW_AUTHOR), origin: Resource::author(PostMother::AUTHOR)),
                RedirectMother::create(id: RedirectIdMother::random()->value(), source: '/tag/ruido.html', target: Resource::term(self::NEW_TAG), origin: Resource::term(PostMother::OTHER_TAG)),
            ],
        );

        PageComponents::one($page, 'Shared:PostMeta', [
            'section' => ['name' => 'Día a día', 'url' => '/dia-a-dia/'],
            'author' => ['name' => 'Ana', 'url' => '/autor/ana.html'],
        ]);
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Calma', 'url' => '/tag/calma.html']]]);
    }

    /**
     * Previewed, a post is dated as it would be published now.
     */
    #[DataProvider('dates')]
    public function testItDatesThePostAsIfPublishedNow(Post $post, bool $updated, ?string $canonical): void
    {
        $page = $this->preview($post, onSite: true);

        PageComponents::one($page, 'Shared:PostMeta', ['updated' => $updated, 'date' => '20 de noviembre de 2026']);
        self::assertSame($canonical ?? RouteNameUrlGenerator::SITE.'/', $page->canonical);
    }

    /**
     * @return iterable<string, array{Post, bool, ?string}>
     */
    public static function dates(): iterable
    {
        yield 'a draft, published today' => [PostMother::publishable(), false, null];
        yield 'a post published before, updated today' => [PostMother::published(publishedTitle: 'Antes'), true, RouteNameUrlGenerator::SITE.'/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html'];
        yield 'a post unpublished, published again today' => [PostMother::unpublished(), true, null];
    }

    /**
     * @param array<string, Term> $others    more terms the CMS has, by id
     * @param list<Redirect>      $redirects of the last addresses of what is off the site
     */
    private function preview(Post $post, bool $onSite, array $others = [], ?Author $otherAuthor = null, array $redirects = []): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $terms = $onSite
            ? [
                PostMother::SECTION => TermMother::published(id: PostMother::SECTION, name: 'Editada', publishedName: 'Publicada'),
                PostMother::TAG => TermMother::published(id: PostMother::TAG, taxonomy: Taxonomy::Tag, name: 'Sensorialidad', slug: 'sensorialidad'),
                PostMother::OTHER_TAG => TermMother::create(id: PostMother::OTHER_TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido'),
            ]
            : [
                PostMother::SECTION => TermMother::create(id: PostMother::SECTION),
                PostMother::OTHER_TAG => TermMother::create(id: PostMother::OTHER_TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido'),
            ];
        $terms += $others;
        $author = $onSite ? AuthorMother::published(id: PostMother::AUTHOR, name: 'Firma editada', publishedName: 'Firma publicada') : AuthorMother::create(id: PostMother::AUTHOR);
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => $terms[$id->value()] ?? null);
        $authorRepository = self::createStub(AuthorRepository::class);
        $authorRepository->method('find')->willReturnCallback(static fn (AuthorId $id): ?Author => match (true) {
            PostMother::AUTHOR === $id->value() => $author,
            null !== $otherAuthor && $otherAuthor->id()->equals($id) => $otherAuthor,
            default => null,
        });
        $authorRepository->method('findByIds')->willReturnCallback(static fn (array $ids): array => [] === array_filter($ids, static fn (mixed $id): bool => $id instanceof AuthorId && $id->equals($author->id())) ? [] : [$author]);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirectRepository = self::createStub(RedirectRepository::class);
        $redirectRepository->method('findByOrigins')->willReturnCallback(static fn (ResourceType $type, array $ids): array => array_values(array_filter(
            $redirects,
            static fn (Redirect $redirect): bool => $type === $redirect->origin()->resource()?->type() && \in_array($redirect->origin()->resource()->id(), $ids, true),
        )));
        $redirects = new CmsRedirectsByOrigin($redirectRepository);

        $page = new PreviewPost(
            $urlGenerator,
            new PreviewLayout($urlGenerator, self::createStub(SettingsRepository::class), $pageRepository, $imageRepository, $redirects),
            new SitePostPage(new RichTextComponents(), $translator),
            new PostUrl($urlGenerator),
            $pageRepository,
            $imageRepository,
            $termRepository,
            $authorRepository,
            $redirects,
        )(PostResponse::fromPost($post));
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
