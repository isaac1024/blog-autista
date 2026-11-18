<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Resolution;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\ExternalComponents;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorResponse;
use Web\Author\Application\AuthorsOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PagesOnSiteFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostCardResponse;
use Web\Post\Application\PostsOnSiteFinder;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Ui\SiteExternalViews;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Application\TermsOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * What a rich text painted on the site links to, its widgets and the
 * captions of its images too: the pages, posts, sections, tags, authors and
 * external resources on the site, each with its current address and name, keyed by the id
 * linked; one off the site, as the one of its kind its last address now
 * leads to. One neither on the site nor redirected isn't there, so its text
 * stays plain and its widget isn't painted; nor is the content painted,
 * which doesn't link itself.
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
final readonly class SiteLinkedContents
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteExternalViews $externalViews,
        private ImageRepository $imageRepository,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content painted, linked by no text of its own
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
            ...$this->resolve(LinkType::Page, $ids(LinkType::Page), $painted, fn (array $ids): array => array_map(fn (PageResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
                'related' => self::related('page', $page->metaDescription, null, null, null),
            ], new PagesOnSiteFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $ids(LinkType::Post), $painted, $this->posts(...)),
            ...$this->resolve(LinkType::Term, $ids(LinkType::Term), $painted, $this->terms(...)),
            ...$this->resolve(LinkType::Author, $ids(LinkType::Author), $painted, $this->authors(...)),
            // No address of the site leads to an external resource, so none is redirected.
            ...($this->externalViews)($ids(LinkType::External)),
        ];
    }

    /**
     * The sections and the tags on the site among those given, read at
     * once, as a link and a card of related content show them.
     *
     * @param list<string> $ids
     *
     * @return array<string, LinkedContent>
     */
    private function terms(array $ids): array
    {
        return array_map(function (TermResponse $term): array {
            $kind = Taxonomy::Section->value === $term->taxonomy ? 'section' : 'tag';

            return [
                'url' => $this->urlGenerator->generate('web_'.$kind, ['slug' => $term->slug]),
                'title' => $term->name,
                'related' => self::related($kind, $term->metaDescription, null, null, null),
            ];
        }, new TermsOnSiteFinder($ids)($this->termRepository));
    }

    /**
     * The posts on the site among those given, as a link and a card of
     * related content show them: the posts at once, then the images they
     * open with, at once too.
     *
     * @param list<string> $ids
     *
     * @return array<string, LinkedContent>
     */
    private function posts(array $ids): array
    {
        $posts = new PostsOnSiteFinder($ids)($this->postRepository);
        $images = new ImagesOnSiteFinder(array_values(array_map(static fn (PostCardResponse $post): string => $post->openingImageId, $posts)))($this->imageRepository);

        return array_map(function (PostCardResponse $post) use ($images): array {
            $image = $images[$post->openingImageId] ?? null;
            $painted = null === $image ? null : SiteMedia::image($image->path, $image->alt, $image->caption);

            return [
                'url' => PostAddressUrl::generate($this->urlGenerator, $post->sectionSlug, $post->day, $post->slug, UrlGeneratorInterface::ABSOLUTE_PATH),
                'title' => $post->title,
                'related' => self::related(
                    'post',
                    $post->metaDescription,
                    null === $painted ? null : ['src' => $painted['src'], 'srcset' => $painted['srcset'], 'width' => $painted['width'], 'height' => $painted['height']],
                    $post->sectionName,
                    $post->firstPublishedAt,
                ),
            ];
        }, $posts);
    }

    /**
     * The authors on the site among those given, read at once, as a link
     * and a card of related content show them.
     *
     * @param list<string> $ids
     *
     * @return array<string, LinkedContent>
     */
    private function authors(array $ids): array
    {
        return array_map(function (AuthorResponse $author): array {
            $avatar = SiteMedia::avatar($author->avatarName, $author->name);

            return [
                'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]),
                'title' => $author->name,
                'related' => self::related('author', $author->metaDescription, ['src' => $avatar['src'], 'srcset' => $avatar['srcset'], 'width' => $avatar['size'], 'height' => $avatar['size']], null, null),
            ];
        }, new AuthorsOnSiteFinder($ids)($this->authorRepository));
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
