<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteMedia;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Post\Application\PostCardResponse;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Taxonomy\Application\TermsOnSiteFinder;
use Web\Taxonomy\Domain\TermRepository;

/**
 * What the site shows of a post in short, wherever it sums one up: in a
 * list, a mosaic, the search, a card of related content or the feed. It
 * gathers it at once for all the posts given: the address of each, its
 * opening image on the site, or the one the address of that image now leads
 * to, if any, and its section by the current name, while on the site or as
 * the section its address now leads to, by the one the post kept otherwise.
 * Its address names the section as SitePostUrls does.
 *
 * @phpstan-import-type CardPost from PostCardsComponent
 *
 * @phpstan-type PostSummary array{url: string, section: string, image: ?ImageResponse}
 */
final readonly class SitePostCards
{
    public function __construct(
        private SitePostUrls $sitePostUrls,
        private ImageRepository $imageRepository,
        private TermRepository $termRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    /**
     * The cards of the posts, in the order given.
     *
     * @param list<PostCardResponse> $posts
     *
     * @return list<CardPost>
     */
    public function __invoke(array $posts): array
    {
        return array_map(static fn (PostCardResponse $post, array $summary): array => [
            'url' => $summary['url'],
            'title' => $post->title,
            'excerpt' => $post->metaDescription,
            'section' => $summary['section'],
            'firstPublishedAt' => $post->firstPublishedAt,
            'image' => self::image($summary['image']),
        ], $posts, $this->summaries($posts, UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    /**
     * What each post is summed up by, in the order given, its address of
     * the reference type given.
     *
     * @param list<PostCardResponse> $posts
     *
     * @return list<PostSummary>
     */
    public function summaries(array $posts, int $referenceType): array
    {
        $images = new ReferenceResolver('image', array_map(static fn (PostCardResponse $post): string => $post->openingImageId, $posts), null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
        $terms = new ReferenceResolver('term', array_map(static fn (PostCardResponse $post): string => $post->sectionId, $posts), null)(
            fn (array $ids): array => new TermsOnSiteFinder($ids)($this->termRepository),
            $this->redirectsByOrigin,
        );

        $urls = ($this->sitePostUrls)(array_map(static fn (PostCardResponse $post): array => ['sectionId' => $post->sectionId, 'day' => $post->day, 'slug' => $post->slug], $posts), $referenceType);

        return array_map(static function (PostCardResponse $post, string $url) use ($terms, $images): array {
            $term = $terms[$post->sectionId] ?? null;

            return [
                'url' => $url,
                'section' => true === $term?->isSection() ? $term->name : $post->sectionName,
                'image' => $images[$post->openingImageId] ?? null,
            ];
        }, $posts, $urls);
    }

    /**
     * @return ?array{src: string, srcset: string, width: int, height: int}
     */
    private static function image(?ImageResponse $image): ?array
    {
        if (null === $image) {
            return null;
        }

        $painted = SiteMedia::image($image->path, $image->alt, $image->caption);

        return ['src' => $painted['src'], 'srcset' => $painted['srcset'], 'width' => $painted['width'], 'height' => $painted['height']];
    }
}
