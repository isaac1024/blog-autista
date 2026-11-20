<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Application\LatestPostsOnSiteFinder;
use Cms\Post\Application\PostOnSiteCardResponse;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Taxonomy\Application\TermsOnSiteFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteMedia;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The posts on the site as cards, as a preview paints them wherever the site
 * sums one up (a list, a mosaic, a card of related content), from what the
 * CMS has on the site, gathered at once for all of them: the opening image on
 * the site, or the one its address now leads to, and the section by its name
 * there, or as the section its address now leads to. Cards lead to the site.
 *
 * @phpstan-import-type CardPost from PostCardsComponent
 */
final readonly class CmsPostCards
{
    public function __construct(
        private PostRepository $postRepository,
        private ImageRepository $imageRepository,
        private TermRepository $termRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private UrlGeneratorInterface $urlGenerator,
        private CmsSectionSlugs $sectionSlugs,
    ) {}

    /**
     * @param ?string      $sectionId the section the posts are under, none for any
     * @param ?string      $authorId  who signs them, none for anyone
     * @param list<string> $tagIds    any of them, none for every post
     *
     * @return list<CardPost>
     */
    public function __invoke(?string $sectionId, ?string $authorId, array $tagIds, int $limit): array
    {
        return $this->cards(new LatestPostsOnSiteFinder($sectionId, $authorId, $tagIds, $limit)($this->postRepository));
    }

    /**
     * The cards of the posts given, in that order, as the site sums them up.
     *
     * @param list<PostOnSiteCardResponse> $posts
     *
     * @return list<CardPost>
     */
    public function cards(array $posts): array
    {
        $images = new ReferenceResolver('image', array_values(array_unique(array_filter(array_map(static fn (PostOnSiteCardResponse $post): ?string => $post->openingImageId, $posts), static fn (?string $id): bool => null !== $id))), null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
        $terms = new ReferenceResolver('term', array_map(static fn (PostOnSiteCardResponse $post): string => $post->sectionId, $posts), null)(
            fn (array $ids): array => new TermsOnSiteFinder($ids)($this->termRepository),
            $this->redirectsByOrigin,
        );

        $slugs = ($this->sectionSlugs)(array_map(static fn (PostOnSiteCardResponse $post): string => $post->sectionId, $posts));

        return array_map(function (PostOnSiteCardResponse $post) use ($terms, $images, $slugs): array {
            $term = $terms[$post->sectionId] ?? null;

            return [
                'url' => PostAddressUrl::generate($this->urlGenerator, $slugs[$post->sectionId] ?? '', $post->day, $post->slug, UrlGeneratorInterface::ABSOLUTE_URL),
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'section' => true === $term?->isSection() ? $term->name : '',
                'firstPublishedAt' => $post->firstPublishedAt,
                'image' => self::image(null === $post->openingImageId ? null : $images[$post->openingImageId] ?? null),
            ];
        }, $posts);
    }

    /**
     * @return ?array{src: string, srcset: string, width: int, height: int}
     */
    private static function image(?ImageOnSiteResponse $image): ?array
    {
        if (null === $image) {
            return null;
        }

        $painted = SiteMedia::image($image->path, $image->alt, $image->caption);

        return ['src' => $painted['src'], 'srcset' => $painted['srcset'], 'width' => $painted['width'], 'height' => $painted['height']];
    }
}
