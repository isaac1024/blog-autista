<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePostPage;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Application\AuthorResponse;
use Web\Author\Domain\AuthorRepository;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Post\Application\PostResponse;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Application\TermResponse;
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
        [$year, $month, $day] = explode('-', $post->day);
        $canonical = $this->urlGenerator->generate('web_post', [
            'section' => $post->sectionSlug,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'slug' => $post->slug,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

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
        $section = $this->terms(Taxonomy::Section, [$post->sectionId])[$post->sectionId] ?? null;
        $author = new ReferenceResolver('author', [$post->authorId], null)(
            fn (array $ids): array => $this->authorsOnSite($ids),
            $this->redirectsByOrigin,
        )[$post->authorId] ?? null;

        $tags = $this->tags($post->tagIds);
        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $opening = $images[$post->openingImageId] ?? null;

        return ($this->sitePostPage)(
            $this->siteLayout->page($post->title)
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
                    ['name' => $author->name ?? $post->authorName, 'url' => null === $author ? null : $this->urlGenerator->generate('web_author', ['slug' => $author->slug], UrlGeneratorInterface::ABSOLUTE_URL)],
                    $section->name ?? $post->sectionName,
                    array_column($tags, 'name'),
                    $this->siteLayout->publisherUrl(),
                )),
            $post->title,
            $post->firstPublishedAt,
            $post->lastPublishedAt,
            null === $section
                ? ['name' => $post->sectionName, 'url' => null]
                : ['name' => $section->name, 'url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug])],
            null === $author
                ? ['name' => $post->authorName, 'url' => null]
                : ['name' => $author->name, 'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug])],
            $tags,
            $opening,
            $post->content,
            $images,
            ($this->linkedContents)($post->content, $images, $post->id),
        );
    }

    /**
     * The tags on the site among those given, in order, each leading to its
     * page, once; a tag off the site, as the one its address now leads to.
     *
     * @param list<string> $ids
     *
     * @return list<array{name: string, url: string}>
     */
    private function tags(array $ids): array
    {
        $tags = [];

        foreach ($this->terms(Taxonomy::Tag, $ids) as $tag) {
            $tags[$tag->slug] = ['name' => $tag->name, 'url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug])];
        }

        return array_values($tags);
    }

    /**
     * The terms of the taxonomy among those given, in order, as the site has
     * them, keyed by the id given; one off the site, as the term of the
     * taxonomy its address now leads to.
     *
     * @param list<string> $ids
     *
     * @return array<string, TermResponse>
     */
    private function terms(Taxonomy $taxonomy, array $ids): array
    {
        $resolved = new ReferenceResolver('term', $ids, null)(
            function (array $ids) use ($taxonomy): array {
                $terms = [];

                foreach ($ids as $id) {
                    $term = new TermOnSiteFinder($taxonomy->value, $id)($this->termRepository);

                    if (null !== $term) {
                        $terms[$id] = $term;
                    }
                }

                return $terms;
            },
            $this->redirectsByOrigin,
        );
        $terms = [];

        foreach ($ids as $id) {
            if (\array_key_exists($id, $resolved)) {
                $terms[$id] = $resolved[$id];
            }
        }

        return $terms;
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
