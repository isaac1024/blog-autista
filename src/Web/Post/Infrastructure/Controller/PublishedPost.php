<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePostPage;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Application\AuthorResponse;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Infrastructure\Ui\AuthorPerson;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Post\Application\PostResponse;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Application\TermsOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Composes a post as the site serves it: its section and its author lead to
 * their pages while they are on the site, and are named as the post was
 * published otherwise; only its tags on the site are shown.
 *
 * It paints from the settings, the images, the pages, the terms and the
 * authors of the site, so it reaches them all.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class PublishedPost
{
    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SitePostPage $sitePostPage,
        private SiteLayout $siteLayout,
        private ImageRepository $imageRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteLinkedContents $linkedContents,
    ) {}

    public function __invoke(PostResponse $post): Page
    {
        $canonical = $this->canonical($post);

        // An image off the site, the opening one too, is painted as the one
        // its address now leads to, if any, so the pages its caption links to
        // are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', [$post->openingImageId, ...$post->content->imageIds()], null)(
                fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        // A section or an author off the site is shown as the one its address
        // now leads to, if any; otherwise by the name it had, as text.
        $terms = $this->terms([$post->sectionId, ...$post->tagIds]);
        $section = $terms[$post->sectionId] ?? null;
        $section = Taxonomy::Section->value === $section?->taxonomy ? $section : null;
        $author = new ReferenceResolver('author', [$post->authorId], null)(
            fn (array $ids): array => $this->authorsOnSite($ids),
            $this->redirectsByOrigin,
        )[$post->authorId] ?? null;

        $tags = $this->tags($post->tagIds, $terms);
        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $opening = $images[$post->openingImageId] ?? null;
        $person = null === $author ? null : AuthorPerson::node($author, $this->urlGenerator);

        ['page' => $layout, 'publisherUrl' => $publisherUrl] = $this->siteLayout->pageWithPublisher($post->title);

        // Who signs it, while on the site, is a node of the graph the post
        // refers to, told once if they publish the blog too.
        if (null !== $person) {
            $layout = $layout->withStructuredData($person['node']);
        }

        return ($this->sitePostPage)(
            $layout
                ->withSearchEngineMetadata($post->metaDescription, $post->robots.',max-image-preview:large', $canonical)
                ->withCountedVisits('post', $post->id)
                ->withStructuredData(StructuredData::webPage('WebPage', $home, $canonical, $post->title, $post->metaDescription, []))
                ->withStructuredData(StructuredData::blogPosting(
                    $canonical,
                    $post->title,
                    $post->metaDescription,
                    null === $opening ? null : StructuredData::absolute($home, $opening['src']),
                    $post->firstPublishedAt,
                    $post->lastPublishedAt,
                    ['name' => $author->name ?? $post->authorName, 'url' => $person['url'] ?? null],
                    $section->name ?? $post->sectionName,
                    array_column($tags, 'name'),
                    $publisherUrl,
                )),
            $post->title,
            $post->firstPublishedAt,
            $post->lastPublishedAt,
            $post->readingMinutes,
            null === $section
                ? ['name' => $post->sectionName, 'url' => null]
                : ['name' => $section->name, 'url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug])],
            $this->signature($post, $author),
            $tags,
            $opening,
            $post->content,
            $images,
            ($this->linkedContents)($post->content, $images, $post->id),
        );
    }

    /**
     * The address the post is served at, the one search engines keep.
     */
    private function canonical(PostResponse $post): string
    {
        return PostAddressUrl::generate($this->urlGenerator, $post->sectionSlug, $post->day, $post->slug, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Who signs the post, leading to their page with their avatar while they
     * are on the site, or as the one their address now leads to; otherwise
     * by the name they had when it was published, as text.
     *
     * @return array{name: string, url: ?string, avatar: ?array{src: string, srcset: string, size: int, alt: string}}
     */
    private function signature(PostResponse $post, ?AuthorResponse $author): array
    {
        return null === $author
            ? ['name' => $post->authorName, 'url' => null, 'avatar' => null]
            : [
                'name' => $author->name,
                'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]),
                'avatar' => SiteMedia::avatar($author->avatarName, $author->name),
            ];
    }

    /**
     * The tags on the site among those given, in order, each leading to its
     * page, once; a tag off the site, as the one its address now leads to,
     * while that is a tag.
     *
     * @param list<string>                $ids
     * @param array<string, TermResponse> $terms as terms() shows them, keyed by the id given
     *
     * @return list<array{name: string, url: string}>
     */
    private function tags(array $ids, array $terms): array
    {
        $tags = [];

        foreach ($ids as $id) {
            $tag = $terms[$id] ?? null;

            if (Taxonomy::Tag->value === $tag?->taxonomy) {
                $tags[$tag->slug] = ['name' => $tag->name, 'url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug])];
            }
        }

        return array_values($tags);
    }

    /**
     * The terms among those given, the section and the tags at once, as the
     * site has them, keyed by the id given; one off the site, as the term its
     * address now leads to. Each tells its taxonomy, which its caller checks.
     *
     * @param list<string> $ids
     *
     * @return array<string, TermResponse>
     */
    private function terms(array $ids): array
    {
        return new ReferenceResolver('term', $ids, null)(
            fn (array $ids): array => new TermsOnSiteFinder($ids)($this->termRepository),
            $this->redirectsByOrigin,
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, AuthorResponse>
     */
    private function authorsOnSite(array $ids): array
    {
        $authors = [];

        foreach ($ids as $id) {
            $author = new AuthorOnSiteFinder($id)($this->authorRepository);

            if (null !== $author) {
                $authors[$id] = $author;
            }
        }

        return $authors;
    }
}
