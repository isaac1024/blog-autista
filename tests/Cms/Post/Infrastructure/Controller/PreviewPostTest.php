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
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\PostMother;
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

    private function preview(Post $post, bool $onSite): Page
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
        $author = $onSite ? AuthorMother::published(id: PostMother::AUTHOR, name: 'Firma editada', publishedName: 'Firma publicada') : AuthorMother::create(id: PostMother::AUTHOR);
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => $terms[$id->value()] ?? null);
        $authorRepository = self::createStub(AuthorRepository::class);
        $authorRepository->method('find')->willReturnCallback(static fn (AuthorId $id): ?Author => PostMother::AUTHOR === $id->value() ? $author : null);
        $authorRepository->method('findByIds')->willReturnCallback(static fn (array $ids): array => [] === array_filter($ids, static fn (mixed $id): bool => $id instanceof AuthorId && $id->equals($author->id())) ? [] : [$author]);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn([]);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirects = new CmsRedirectsByOrigin(self::createStub(RedirectRepository::class));

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
