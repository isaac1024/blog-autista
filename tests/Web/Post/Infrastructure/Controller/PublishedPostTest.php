<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\AvatarCopy;
use Shared\Infrastructure\Ui\Page\Page as SitePage;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SiteMedia;
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
use Web\External\Domain\ExternalResourceRepository as WebExternalResourceRepository;
use Web\External\Infrastructure\Ui\SiteExternalViews;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostResponse;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Post\Infrastructure\Controller\PublishedPost;
use Web\Redirect\Domain\Redirect;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother;

final class PublishedPostTest extends TestCase
{
    private const string SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    private const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    private const string OTHER_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a03';

    private const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    private const string THIRD_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a04';

    private const string NEW_SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0b01';

    private const string NEW_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0b02';

    private const string NEW_AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b03';

    private const string NEW_IMAGE = '01a1b801-ce58-723d-8234-b76d9105d0b0';

    /**
     * The largest copy of the opening image of the post, which it is shared with.
     */
    private const string OPENING_SRC = '/media/2026/11/'.CmsPostPublishedMother::OPENING_IMAGE.'-1200w.webp';

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

        PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => 'Día a día', 'url' => '/dia-a-dia/'],
            'author' => ['name' => 'Isaac', 'url' => '/autor/isaac.html', 'avatar' => SiteMedia::avatar(AvatarCopy::named('isaac', '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp'), 'Isaac')],
        ]);
        // Only the tags on the site, in order.
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Ruido', 'url' => '/tag/ruido.html']]]);
        self::assertSame(RouteNameUrlGenerator::SITE.'/vida-diaria/2026/11/07/mi-primer-dia.html', $page->canonical);
        self::assertSame('index,follow,max-image-preview:large', $page->robots);
        // Its visits are counted as those of the post.
        self::assertSame(['type' => 'post', 'id' => PostIdMother::DEFAULT_VALUE], $page->countedContent);
    }

    public function testItNamesTheSectionAndTheAuthorOffTheSiteAsTheyWereWhenPublishedWithoutLinks(): void
    {
        $page = $this->compose([self::TAG], terms: [self::SECTION => self::term('tag', 'Vida diaria', 'vida-diaria')]);

        PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => 'Vida diaria', 'url' => null],
            'author' => ['name' => 'Isaac Abreu', 'url' => null, 'avatar' => null],
        ]);
        self::assertSame([], PageComponents::find($page, 'Shared:PostTags', []));
    }

    /**
     * A section, an author or a tag off the site is shown as the one its
     * last address now leads to, when that is one of its kind on the site;
     * a tag once, however many lead to it.
     */
    public function testItShowsWhatIsOffTheSiteAsWhatItsAddressNowLeadsTo(): void
    {
        $page = $this->compose(
            [self::TAG, self::OTHER_TAG, self::THIRD_TAG],
            terms: [
                self::NEW_SECTION => self::term('section', 'Día a día', 'dia-a-dia'),
                self::NEW_TAG => self::term('tag', 'Calma', 'calma'),
            ],
            authors: [self::NEW_AUTHOR => self::author('Isaac', 'isaac')],
            redirects: [
                new Redirect('term', self::NEW_SECTION, self::SECTION),
                new Redirect('author', self::NEW_AUTHOR, self::AUTHOR),
                new Redirect('term', self::NEW_TAG, self::TAG),
                new Redirect('term', self::NEW_TAG, self::OTHER_TAG),
                // A tag never shows as a section.
                new Redirect('term', self::NEW_SECTION, self::THIRD_TAG),
            ],
        );

        PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => 'Día a día', 'url' => '/dia-a-dia/'],
            'author' => ['name' => 'Isaac', 'url' => '/autor/isaac.html', 'avatar' => SiteMedia::avatar(AvatarCopy::named('isaac', '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp'), 'Isaac')],
        ]);
        PageComponents::one($page, 'Shared:PostTags', ['tags' => [['name' => 'Calma', 'url' => '/tag/calma.html']]]);
    }

    /**
     * What leads to a section where a tag is, or nowhere, leaves it as it
     * was when published, without a link.
     */
    public function testItNamesASectionWhoseAddressLeadsToNoSectionAsItWas(): void
    {
        $page = $this->compose([], terms: [self::NEW_TAG => self::term('tag', 'Calma', 'calma')], redirects: [
            new Redirect('term', self::NEW_TAG, self::SECTION),
            new Redirect(null, null, self::AUTHOR),
        ]);

        PageComponents::one($page, 'Shared:PostHeader', [
            'section' => ['name' => 'Vida diaria', 'url' => null],
            'author' => ['name' => 'Isaac Abreu', 'url' => null, 'avatar' => null],
        ]);
    }

    /**
     * The opening image comes first in the text, fetched before anything
     * else, and is the one the post is shared with.
     */
    public function testItOpensWithItsImageAndIsSharedWithIt(): void
    {
        $page = $this->compose([], images: [self::image(CmsPostPublishedMother::OPENING_IMAGE, 'Andén del metro vacío')]);

        $prose = PageComponents::one($page, 'Shared:Prose', []);
        $opening = PageComponents::within($prose)[0] ?? self::fail('The post has no text.');
        self::assertSame('Shared:ContentImage', $opening->name);
        self::assertSame(self::OPENING_SRC, $opening->props['src']);
        self::assertSame('Andén del metro vacío', $opening->props['alt']);
        self::assertTrue($opening->props['opening']);
        self::assertSame(['src' => self::OPENING_SRC, 'width' => 1200, 'height' => 675, 'alt' => 'Andén del metro vacío'], $page->image);
    }

    /**
     * The same image may open the post and sit in its text, where it waits
     * until it is about to be seen, as any image there.
     */
    public function testItPaintsTheOpeningImageInTheTextTooAsAnyOther(): void
    {
        $page = $this->compose([], changes: ['content' => ['version' => 1, 'blocks' => [['type' => 'image', 'id' => CmsPostPublishedMother::OPENING_IMAGE]]]], images: [self::image(CmsPostPublishedMother::OPENING_IMAGE, 'Andén del metro vacío')]);

        self::assertSame([true, false], array_map(
            static fn (PageComponent $image): mixed => $image->props['opening'],
            PageComponents::find($page, 'Shared:ContentImage', ['alt' => 'Andén del metro vacío']),
        ));
    }

    /**
     * An opening image off the site is shown as the one its address now
     * leads to, and the post is shared with that one.
     */
    public function testItOpensWithTheImageTheAddressOfItsOwnNowLeadsTo(): void
    {
        $page = $this->compose([], images: [self::image(self::NEW_IMAGE, 'Vagón en calma')], redirects: [
            new Redirect('image', self::NEW_IMAGE, CmsPostPublishedMother::OPENING_IMAGE),
        ]);

        $opening = PageComponents::one($page, 'Shared:ContentImage', ['opening' => true]);
        self::assertSame('Vagón en calma', $opening->props['alt']);
        self::assertSame('Vagón en calma', $page->image['alt'] ?? null);
    }

    /**
     * With its opening image off the site and nothing in its place, the
     * post opens with its text and isn't shared with any image of its own.
     */
    public function testItOpensWithItsTextWhileItsImageIsOffTheSite(): void
    {
        $page = $this->compose([]);

        self::assertSame([], PageComponents::find($page, 'Shared:ContentImage', []));
        self::assertNull($page->image);
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('dates')]
    public function testItDatesThePostByItsLastPublicationAndSaysWhetherItWasUpdated(array $changes, bool $updated, string $date): void
    {
        PageComponents::one($this->compose([], changes: $changes), 'Shared:PostHeader', ['updated' => $updated, 'date' => $date]);
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
     * The page tells search engines it holds a post: its title, summary and
     * image, when it was first and last published, its section, its tags,
     * and who signs it, by the page of that author while on the site.
     */
    public function testItTellsSearchEnginesItIsAPost(): void
    {
        $page = $this->compose(
            [self::TAG, self::OTHER_TAG],
            terms: [
                self::SECTION => self::term('section', 'Día a día', 'dia-a-dia'),
                self::OTHER_TAG => self::term('tag', 'Ruido', 'ruido'),
            ],
            authors: [self::AUTHOR => self::author('Isaac', 'isaac')],
            images: [self::image(CmsPostPublishedMother::OPENING_IMAGE, 'Andén del metro vacío')],
        );
        $canonical = RouteNameUrlGenerator::SITE.'/vida-diaria/2026/11/07/mi-primer-dia.html';

        self::assertSame([
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => 'Mi primer día sin ruido',
                'description' => 'Qué cambió el primer día con tapones.',
                'isPartOf' => ['@id' => RouteNameUrlGenerator::SITE.'/#website'],
                'inLanguage' => 'es',
            ],
            [
                '@type' => 'BlogPosting',
                '@id' => $canonical.'#article',
                'mainEntityOfPage' => ['@id' => $canonical.'#webpage'],
                'headline' => 'Mi primer día sin ruido',
                'description' => 'Qué cambió el primer día con tapones.',
                'image' => RouteNameUrlGenerator::SITE.self::OPENING_SRC,
                'datePublished' => ClockMother::now()->format(\DATE_ATOM),
                'dateModified' => ClockMother::now()->format(\DATE_ATOM),
                'author' => ['@id' => RouteNameUrlGenerator::SITE.'/autor/isaac.html#person'],
                'articleSection' => 'Día a día',
                'keywords' => 'Ruido',
                'inLanguage' => 'es',
            ],
        ], $page->structuredData);
    }

    /**
     * An author off the site is named as the post kept them, with no page;
     * a post with no image nor tags on the site tells none.
     */
    public function testItTellsSearchEnginesOfWhatIsOffTheSiteByWhatThePostKept(): void
    {
        $page = $this->compose([self::TAG]);

        $posting = $page->structuredData[1] ?? self::fail('No post was told.');
        self::assertSame(['@type' => 'Person', 'name' => 'Isaac Abreu'], $posting['author']);
        self::assertSame('Vida diaria', $posting['articleSection']);
        self::assertArrayNotHasKey('image', $posting);
        self::assertArrayNotHasKey('keywords', $posting);
        self::assertArrayNotHasKey('publisher', $posting);
    }

    /**
     * The author of the blog publishes every post, whoever signs it.
     */
    public function testItTellsSearchEnginesTheAuthorOfTheBlogPublishesThePost(): void
    {
        $page = $this->compose(
            [],
            authors: [self::NEW_AUTHOR => self::author('Ana', 'ana')],
            settings: Settings::publish(CmsSettingsPublishedMother::create(defaultImageId: null, mainMenu: [], footerMenu: [], authorId: self::NEW_AUTHOR)),
        );

        $posting = array_values(array_filter($page->structuredData, static fn (array $node): bool => 'BlogPosting' === $node['@type']))[0] ?? self::fail('No post was told.');
        self::assertSame(['@id' => RouteNameUrlGenerator::SITE.'/autor/ana.html#person'], $posting['publisher']);
        self::assertSame(['@type' => 'Person', 'name' => 'Isaac Abreu'], $posting['author']);
    }

    /**
     * @param list<string>          $tags
     * @param array<string, Term>   $terms     by id
     * @param array<string, Author> $authors   by id
     * @param array<string, mixed>  $changes
     * @param list<Redirect>        $redirects of the last addresses of what is off the site
     * @param list<Image>           $images    on the site
     * @param ?Settings             $settings  on the site, none by default
     */
    private function compose(array $tags, array $terms = [], array $authors = [], array $changes = [], array $redirects = [], array $images = [], ?Settings $settings = null): SitePage
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => $terms[$id->value()] ?? null);
        $authorRepository = self::createStub(AuthorRepository::class);
        $authorRepository->method('find')->willReturnCallback(static fn (AuthorId $id): ?Author => $authors[$id->value()] ?? null);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(
            $images,
            static fn (Image $image): bool => [] !== array_filter($ids, static fn (mixed $id): bool => $id instanceof ImageId && $id->equals($image->id())),
        )));
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn([]);
        $redirectRepository = self::createStub(RedirectRepository::class);
        $redirectRepository->method('findByOrigins')->willReturnCallback(static fn (string $type, array $ids): array => array_values(array_filter(
            $redirects,
            static fn (Redirect $redirect): bool => \in_array($redirect->originId(), $ids, true),
        )));
        $redirects = new SiteRedirectsByOrigin($redirectRepository);

        $post = Post::publish(CmsPostPublished::fromPrimitives(
            PostIdMother::DEFAULT_VALUE,
            $changes + CmsPostPublishedMother::body(tagIds: $tags) + ['revision' => 1],
        ));

        $settingsRepository = self::createStub(SettingsRepository::class);
        $settingsRepository->method('find')->willReturn($settings);

        $page = new PublishedPost(
            $urlGenerator,
            new SitePostPage(new RichTextComponents(), $translator),
            new SiteLayout($urlGenerator, $settingsRepository, $imageRepository, $redirects, new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $redirects, new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator)), $authorRepository),
            $imageRepository,
            $termRepository,
            $authorRepository,
            $redirects,
            new SiteLinkedContents($urlGenerator, $pageRepository, self::createStub(PostRepository::class), $termRepository, $authorRepository, $redirects, new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pageRepository, $urlGenerator)),
        )(PostResponse::fromPost($post));
        ComponentContracts::assertHonoured($page);

        return $page;
    }

    private static function image(string $id, string $alt): Image
    {
        return Image::publish(CmsImagePublishedMother::create(id: $id, alt: $alt, path: '2026/11/'.$id.'.webp'));
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
