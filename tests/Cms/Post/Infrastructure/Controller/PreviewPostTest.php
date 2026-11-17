<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Infrastructure\Controller\AvatarUrl;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Infrastructure\Controller\ExternalImageUrl;
use Cms\External\Infrastructure\Ui\CmsExternalViews;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Infrastructure\Controller\PostUrl;
use Cms\Post\Infrastructure\Controller\PreviewPost;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SitePostPage;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\UriSigner;

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

        $header = PageComponents::one($page, 'Shared:PostHeader', [
            'title' => PostMother::TITLE,
            'readingMinutes' => 1,
            'section' => ['name' => 'Publicada', 'url' => '/vida-diaria/'],
        ]);
        self::assertAuthorOnSite('Firma publicada', '/autor/isaac.html', $header);
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Sensorialidad', 'url' => '/tag/sensorialidad.html']]]);
        self::assertSame('noindex,nofollow', $page->robots);
    }

    /**
     * The opening image saved is previewed as the site would paint it while
     * it is on the site, and the preview is shared with it.
     */
    public function testItOpensWithTheImageSavedWhileItIsOnTheSite(): void
    {
        $page = $this->preview(PostMother::publishable(), onSite: true, images: [PostMother::OPENING_IMAGE => ImageMother::published(id: PostMother::OPENING_IMAGE, alt: 'Andén del metro vacío')]);

        $opening = PageComponents::one($page, 'Shared:ContentImage', ['opening' => true, 'alt' => 'Andén del metro vacío']);
        self::assertSame($opening->props['src'], $page->image['src'] ?? null);
    }

    public function testItOpensWithItsTextWhileItsImageIsOffTheSiteOrUnchosen(): void
    {
        self::assertSame([], PageComponents::find($this->preview(PostMother::publishable(), onSite: true), 'Shared:ContentImage', []));
        self::assertSame([], PageComponents::find($this->preview(PostMother::create(), onSite: true, images: [PostMother::OPENING_IMAGE => ImageMother::published(id: PostMother::OPENING_IMAGE)]), 'Shared:ContentImage', []));
    }

    public function testItNamesTheSectionAndTheAuthorOffTheSiteAsTheyAreSavedWithoutLinks(): void
    {
        $page = $this->preview(PostMother::publishable(tags: [PostMother::OTHER_TAG]), onSite: false);

        PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => TermMother::NAME, 'url' => null],
            'author' => ['name' => AuthorMother::NAME, 'url' => null, 'avatar' => null],
        ]);
        self::assertSame([], PageComponents::find($page, 'Shared:PostTags', []));
        self::assertSame(RouteNameUrlGenerator::SITE.'/', $page->canonical);
    }

    public function testItNamesNoSectionNorAuthorOfAnUnfiledPost(): void
    {
        PageComponents::one($this->preview(PostMother::create(), onSite: true), 'Shared:PostHeader', [
            'section' => ['name' => '', 'url' => null],
            'author' => ['name' => '', 'url' => null, 'avatar' => null],
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

        $header = PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => 'Día a día', 'url' => '/dia-a-dia/'],
        ]);
        self::assertAuthorOnSite('Ana', '/autor/ana.html', $header);
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Calma', 'url' => '/tag/calma.html']]]);
    }

    /**
     * Previewed, a post is dated as it would be published now.
     */
    #[DataProvider('dates')]
    public function testItDatesThePostAsIfPublishedNow(Post $post, bool $updated, ?string $canonical): void
    {
        $page = $this->preview($post, onSite: true);

        PageComponents::one($page, 'Shared:PostHeader', ['updated' => $updated, 'date' => '20 de noviembre de 2026']);
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
     * @param array<string, Term>  $others    more terms the CMS has, by id
     * @param list<Redirect>       $redirects of the last addresses of what is off the site
     * @param array<string, Image> $images    the images of the library, by id
     */
    private function preview(Post $post, bool $onSite, array $others = [], ?Author $otherAuthor = null, array $redirects = [], array $images = []): Page
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
        $imageRepository->method('find')->willReturnCallback(static fn (ImageId $id): ?Image => $images[$id->value()] ?? null);
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
            new PreviewLayout($urlGenerator, self::createStub(SettingsRepository::class), $imageRepository, $redirects, new CmsLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects, new CmsExternalViews(self::createStub(ExternalResourceRepository::class), $pageRepository, $urlGenerator, new ExternalImageUrl($urlGenerator, new SignedUrls(new UriSigner('secret')))), self::createStub(ImageRepository::class), new AvatarUrl($urlGenerator, new SignedUrls(new UriSigner('secret')))), self::createStub(PageRepository::class)),
            new SitePostPage(new RichTextComponents(), $translator),
            new PostUrl($urlGenerator),
            $imageRepository,
            $termRepository,
            $authorRepository,
            $redirects,
            new CmsLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), $termRepository, $authorRepository, $redirects, new CmsExternalViews(self::createStub(ExternalResourceRepository::class), $pageRepository, $urlGenerator, new ExternalImageUrl($urlGenerator, new SignedUrls(new UriSigner('secret')))), self::createStub(ImageRepository::class), new AvatarUrl($urlGenerator, new SignedUrls(new UriSigner('secret')))),
            new AvatarUrl($urlGenerator, new SignedUrls(new UriSigner('secret'))),
        )(PostResponse::fromPost($post));
        ComponentContracts::assertHonoured($page);

        return $page;
    }

    /**
     * The author leads to their page by their name there, with the avatar
     * they have on the site, from the CMS by a signed link, as on the
     * preview of the author.
     */
    private static function assertAuthorOnSite(string $name, string $url, PageComponent $header): void
    {
        $author = $header->props['author'] ?? null;
        self::assertIsArray($author);
        self::assertSame([$name, $url], [$author['name'] ?? null, $author['url'] ?? null]);
        $avatar = $author['avatar'] ?? null;
        self::assertIsArray($avatar);
        $src = $avatar['src'] ?? null;
        self::assertIsString($src);
        self::assertStringContainsString(AuthorMother::AVATAR, $src);
        self::assertStringContainsString('_hash=', $src);
        self::assertSame(['', 400, $name], [$avatar['srcset'] ?? null, $avatar['size'] ?? null, $avatar['alt'] ?? null]);
    }
}
