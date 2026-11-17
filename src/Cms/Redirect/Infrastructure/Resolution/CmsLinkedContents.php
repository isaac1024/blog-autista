<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Resolution;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarFile;
use Cms\Author\Infrastructure\Controller\AvatarUrl;
use Cms\External\Infrastructure\Ui\CmsExternalViews;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostOnSiteResponse;
use Cms\Post\Application\PostsOnSiteFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Application\TermOnSiteFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Clock;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\ExternalComponents;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a rich text previewed links to, its widgets and the captions of its
 * images too, as the site would show it once published: the pages, posts,
 * sections, tags and authors on the site, each with its address and name
 * there, keyed by the id linked; one off the site, as the one of its kind
 * its last address now leads to, as SiteLinkedContents does on the site.
 *
 * It reaches every module a link can lead to, so its coupling and what it
 * takes grow with the kinds of content and say nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type LinkedContent from RichTextComponents
 * @phpstan-import-type RelatedView from RichTextComponents
 */
final readonly class CmsLinkedContents
{
    /**
     * How long the link to the avatar of an author lasts, from when the
     * preview is painted, as on the preview of the author.
     */
    private const string AVATAR_VALIDITY = 'P1D';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsExternalViews $externalViews,
        private ImageRepository $imageRepository,
        private AvatarUrl $avatarUrl,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content previewed, linked by no text of its own
     *
     * @return array<string, LinkedContent>
     */
    public function __invoke(RichText $content, array $images, ?string $painted): array
    {
        return $this->linked(static fn (LinkType $type): array => SitePage::linkedIds($content, $images, $type), $painted);
    }

    /**
     * What the entries of menus lead to, as for a text: those of each kind
     * on the site, or redirected, with their address and name, keyed by id.
     *
     * @param list<array{type: string, id: string, label: ?string}> $entries
     *
     * @return array<string, LinkedContent>
     */
    public function menuTargets(array $entries): array
    {
        return $this->linked(static fn (LinkType $type): array => array_values(array_map(
            static fn (array $entry): string => $entry['id'],
            array_filter($entries, static fn (array $entry): bool => $type->value === $entry['type']),
        )), null);
    }

    /**
     * @param \Closure(LinkType): list<string> $ids what is linked of each kind
     *
     * @return array<string, LinkedContent>
     */
    private function linked(\Closure $ids, ?string $painted): array
    {
        return [
            ...$this->resolve(LinkType::Page, $ids(LinkType::Page), $painted, fn (array $ids): array => array_map(fn (PageOnSiteResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
                'related' => self::related('page', $page->metaDescription, null, null, null),
            ], new PagesOnSiteFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $ids(LinkType::Post), $painted, fn (array $ids): array => array_map(function (PostOnSiteResponse $post): array {
                $image = null === $post->openingImageId ? null : new ImagesOnSiteFinder([$post->openingImageId])($this->imageRepository)[$post->openingImageId] ?? null;
                $painted = null === $image ? null : SiteMedia::image($image->path, $image->alt, $image->caption);

                return [
                    'url' => PostAddressUrl::generate($this->urlGenerator, $post->address->section, $post->address->day, $post->address->slug, UrlGeneratorInterface::ABSOLUTE_PATH),
                    'title' => $post->title,
                    'related' => self::related(
                        'post',
                        $post->excerpt,
                        null === $painted ? null : ['src' => $painted['src'], 'srcset' => $painted['srcset'], 'width' => $painted['width'], 'height' => $painted['height']],
                        new TermOnSiteFinder(Taxonomy::Section, $post->sectionId)($this->termRepository)?->name,
                        $post->firstPublishedAt,
                    ),
                ];
            }, new PostsOnSiteFinder($ids)($this->postRepository))),
            ...$this->resolve(LinkType::Term, $ids(LinkType::Term), $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $section = new TermOnSiteFinder(Taxonomy::Section, $id)($this->termRepository);

                if (null !== $section) {
                    return ['url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug]), 'title' => $section->name, 'related' => self::related('section', $section->metaDescription, null, null, null)];
                }

                $tag = new TermOnSiteFinder(Taxonomy::Tag, $id)($this->termRepository);

                return null === $tag ? null : ['url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug]), 'title' => $tag->name, 'related' => self::related('tag', $tag->metaDescription, null, null, null)];
            })),
            ...$this->resolve(LinkType::Author, $ids(LinkType::Author), $painted, fn (array $ids): array => $this->each($ids, $this->author(...))),
            // No address of the site leads to an external resource, so none is redirected.
            ...($this->externalViews)($ids(LinkType::External)),
        ];
    }

    /**
     * An author on the site, as a link and a card of related content show it.
     *
     * @return ?LinkedContent
     */
    private function author(string $id): ?array
    {
        $author = new AuthorOnSiteFinder($id)($this->authorRepository);

        if (null === $author) {
            return null;
        }

        // Its avatar is the CMS's file, as on the preview of the author.
        $avatar = null === $author->avatar ? null : [
            'src' => ($this->avatarUrl)($author->id, $author->avatar, Clock::now()->add(new \DateInterval(self::AVATAR_VALIDITY))),
            'srcset' => '',
            'width' => AvatarFile::SIZE,
            'height' => AvatarFile::SIZE,
        ];

        return [
            'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]),
            'title' => $author->name,
            'related' => self::related('author', $author->metaDescription, $avatar, null, null),
        ];
    }

    /**
     * @param list<string>                                         $ids
     * @param \Closure(list<string>): array<string, LinkedContent> $onSite
     *
     * @return array<string, LinkedContent>
     */
    private function resolve(LinkType $type, array $ids, ?string $painted, \Closure $onSite): array
    {
        return new ReferenceResolver($type->value, $ids, $painted)($onSite, $this->redirectsByOrigin);
    }

    /**
     * @param list<string>                     $ids
     * @param \Closure(string): ?LinkedContent $find
     *
     * @return array<string, LinkedContent>
     */
    private function each(array $ids, \Closure $find): array
    {
        $found = [];

        foreach ($ids as $id) {
            $content = $find($id);

            if (null !== $content) {
                $found[$id] = $content;
            }
        }

        return $found;
    }

    /**
     * What a card of related content shows of a content of the blog, beside
     * its name.
     *
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     *
     * @return RelatedView
     */
    private static function related(string $kind, ?string $summary, ?array $image, ?string $section, ?\DateTimeImmutable $published): array
    {
        return ['kind' => $kind, 'summary' => $summary, 'image' => $image, 'section' => $section, 'published' => $published];
    }
}
