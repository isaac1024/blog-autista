<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Shared\Infrastructure\Ui\SitePostPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostResponse;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermOnSiteFinder;
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
        private PageRepository $pageRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
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

        // An image off the site is painted as the one its address now leads
        // to, if any, so the pages its caption links to are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $post->content->imageIds(), null)(
                fn (array $ids): array => new PublishedImagesFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        $section = new TermOnSiteFinder(Taxonomy::Section->value, $post->sectionId)($this->termRepository);
        $author = new AuthorOnSiteFinder($post->authorId)($this->authorRepository);

        return ($this->sitePostPage)(
            $this->siteLayout->page($post->title)
                ->withSearchEngineMetadata($post->metaDescription, $post->robots.',max-image-preview:large', $canonical),
            $post->title,
            $post->firstPublishedAt,
            $post->lastPublishedAt,
            null === $section
                ? ['name' => $post->sectionName, 'url' => null]
                : ['name' => $section->name, 'url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug])],
            null === $author
                ? ['name' => $post->authorName, 'url' => null]
                : ['name' => $author->name, 'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug])],
            $this->tags($post->tagIds),
            $post->content,
            $images,
            $this->linkedPages(SitePage::linkedPageIds($post->content, $images)),
        );
    }

    /**
     * The tags on the site among those given, in order, each leading to its page.
     *
     * @param list<string> $ids
     *
     * @return list<array{name: string, url: string}>
     */
    private function tags(array $ids): array
    {
        $tags = [];

        foreach ($ids as $id) {
            $tag = new TermOnSiteFinder(Taxonomy::Tag->value, $id)($this->termRepository);

            if (null !== $tag) {
                $tags[] = ['name' => $tag->name, 'url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug])];
            }
        }

        return $tags;
    }

    /**
     * The published pages among those linked, with their current address,
     * keyed by id; one off the site, as the page its address now leads to.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function linkedPages(array $pageIds): array
    {
        return array_map(fn (PageResponse $target): array => [
            'url' => null === $target->slug
                ? $this->urlGenerator->generate('web_home')
                : $this->urlGenerator->generate('web_page', ['slug' => $target->slug]),
            'title' => $target->title,
        ], new ReferenceResolver('page', $pageIds, null)(
            fn (array $ids): array => new PublishedPagesFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        ));
    }
}
