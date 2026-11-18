<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Application\AuthorOnSiteResponse;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Application\PostResponse;
use Cms\Shared\Infrastructure\Http\AvatarUrl;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Shared\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Shared\Infrastructure\Ui\PreviewLayout;
use Cms\Taxonomy\Application\TermOnSiteResponse;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Application\TermsOnSiteFinder;
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
     * How long the link to the avatar of its author lasts, from when the
     * preview is painted, as on the preview of the author.
     */
    private const string AVATAR_VALIDITY = 'P1D';

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
        private AvatarUrl $avatarUrl,
    ) {}

    public function __invoke(PostResponse $post): Page
    {
        $content = RichText::fromArray($post->content);
        $images = array_map(
            static fn (ImageOnSiteResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', [...null === $post->openingImageId ? [] : [$post->openingImageId], ...$content->imageIds()], null)(
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
            $content->readingMinutes(),
            $this->section($post->sectionId),
            $this->author($post->authorId),
            $this->tags($post->tagIds),
            null === $post->openingImageId ? null : $images[$post->openingImageId] ?? null,
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
        $onSite = null === $id ? null : $this->terms(TermResponse::SECTION, [$id])[$id] ?? null;

        if (null !== $onSite) {
            return ['name' => $onSite->name, 'url' => $this->urlGenerator->generate('web_section', ['slug' => $onSite->slug])];
        }

        $saved = null === $id ? [] : new TermsOfTaxonomyByIdFinder(TermResponse::SECTION, [$id])($this->termRepository);

        return ['name' => $saved[0]->name ?? '', 'url' => null];
    }

    /**
     * The author as the site names it, leading to its page, with the avatar
     * it has there, while it is on the site, or the one its address now
     * leads to; otherwise as it is saved, as text, and none while unsigned.
     *
     * @return array{name: string, url: ?string, avatar: ?array{src: string, srcset: string, size: int, alt: string}}
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
            return [
                'name' => $onSite->name,
                'url' => $this->urlGenerator->generate('web_author', ['slug' => $onSite->slug]),
                'avatar' => null === $onSite->avatar ? null : [
                    'src' => ($this->avatarUrl)($onSite->id, $onSite->avatar, Clock::now()->add(new \DateInterval(self::AVATAR_VALIDITY))),
                    'srcset' => '',
                    'size' => AuthorOnSiteResponse::AVATAR_SIZE,
                    'alt' => $onSite->name,
                ],
            ];
        }

        $saved = null === $id ? [] : new AuthorsFinder([$id])($this->authorRepository);

        return ['name' => $saved[0]->name ?? '', 'url' => null, 'avatar' => null];
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

        foreach ($this->terms(TermResponse::TAG, $ids) as $tag) {
            $tags[$tag->slug] = ['name' => $tag->name, 'url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug])];
        }

        return array_values($tags);
    }

    /**
     * The terms of the taxonomy among those given, in order, as they are on
     * the site, read at once, keyed by the id given; one off the site, as the
     * term of the taxonomy its address now leads to.
     *
     * @param string       $taxonomy TermResponse::SECTION or TermResponse::TAG
     * @param list<string> $ids
     *
     * @return array<string, TermOnSiteResponse>
     */
    private function terms(string $taxonomy, array $ids): array
    {
        $resolved = new ReferenceResolver('term', $ids, null)(
            fn (array $ids): array => array_filter(
                new TermsOnSiteFinder($ids)($this->termRepository),
                static fn (TermOnSiteResponse $term): bool => $taxonomy === $term->taxonomy,
            ),
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
