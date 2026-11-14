<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Application\PostResponse;
use Cms\Redirect\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Cms\Taxonomy\Application\TermOnSiteFinder;
use Cms\Taxonomy\Application\TermOnSiteResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Clock;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePostPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Composes the version of a post last saved as the site would serve it if
 * published now, in the chrome the site shows now: its section and its
 * author lead to their pages while they are on the site and are named as
 * text otherwise, only its tags on the site are shown, and it is dated as it
 * would be published today. Links lead to the site.
 *
 * Search engines never index a preview nor follow its links, whatever the
 * post asks of them.
 *
 * It paints from the settings, the images, the pages, the terms and the
 * authors, as the site does, so it reaches them all.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class PreviewPost
{
    public const string ROBOTS = 'noindex,nofollow';

    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PreviewLayout $previewLayout,
        private SitePostPage $sitePostPage,
        private PostUrl $postUrl,
        private ImageRepository $imageRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsLinkedContents $linkedContents,
    ) {}

    public function __invoke(PostResponse $post): Page
    {
        $content = RichText::fromArray($post->content);
        $images = array_map(
            static fn (ImageOnSiteResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $content->imageIds(), null)(
                fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );
        $now = Clock::now();

        return ($this->sitePostPage)(
            $this->previewLayout->page($post->title)->withSearchEngineMetadata(
                '' === $post->metaDescription ? null : $post->metaDescription,
                self::ROBOTS,
                $this->postUrl->onSite($post, UrlGeneratorInterface::ABSOLUTE_URL)
                    ?? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
            ),
            $post->title,
            $post->firstPublishedAt ?? $now,
            $now,
            $this->section($post->sectionId),
            $this->author($post->authorId),
            $this->tags($post->tagIds),
            $content,
            $images,
            ($this->linkedContents)($content, $images, $post->id),
        );
    }

    /**
     * The section as the site names it, leading to its page, while it is on
     * the site, or the one its address now leads to; otherwise as it is
     * saved, as text, and none while unfiled.
     *
     * @return array{name: string, url: ?string}
     */
    private function section(?string $id): array
    {
        $onSite = null === $id ? null : $this->terms(Taxonomy::Section, [$id])[$id] ?? null;

        if (null !== $onSite) {
            return ['name' => $onSite->name, 'url' => $this->urlGenerator->generate('web_section', ['slug' => $onSite->slug])];
        }

        $saved = null === $id ? [] : new TermsOfTaxonomyByIdFinder(Taxonomy::Section, [$id])($this->termRepository);

        return ['name' => $saved[0]->name ?? '', 'url' => null];
    }

    /**
     * The author as the site names it, leading to its page, while it is on
     * the site, or the one its address now leads to; otherwise as it is
     * saved, as text, and none while unsigned.
     *
     * @return array{name: string, url: ?string}
     */
    private function author(?string $id): array
    {
        $onSite = null === $id ? null : new ReferenceResolver('author', [$id], null)(
            function (array $ids): array {
                $authors = [];

                foreach ($ids as $candidate) {
                    $author = new AuthorOnSiteFinder($candidate)($this->authorRepository);

                    if (null !== $author) {
                        $authors[$candidate] = $author;
                    }
                }

                return $authors;
            },
            $this->redirectsByOrigin,
        )[$id] ?? null;

        if (null !== $onSite) {
            return ['name' => $onSite->name, 'url' => $this->urlGenerator->generate('web_author', ['slug' => $onSite->slug])];
        }

        $saved = null === $id ? [] : new AuthorsFinder([$id])($this->authorRepository);

        return ['name' => $saved[0]->name ?? '', 'url' => null];
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
     * The terms of the taxonomy among those given, in order, as they are on
     * the site, keyed by the id given; one off the site, as the term of the
     * taxonomy its address now leads to.
     *
     * @param list<string> $ids
     *
     * @return array<string, TermOnSiteResponse>
     */
    private function terms(Taxonomy $taxonomy, array $ids): array
    {
        $resolved = new ReferenceResolver('term', $ids, null)(
            function (array $ids) use ($taxonomy): array {
                $terms = [];

                foreach ($ids as $id) {
                    $term = new TermOnSiteFinder($taxonomy, $id)($this->termRepository);

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
}
