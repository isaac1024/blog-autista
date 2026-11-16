<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Ui;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteMedia;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Post\Application\PostResponse;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * What the cards of posts of the site show, from what the site has of them,
 * gathered at once for all of them: the opening image on the site, or the
 * one its address now leads to, if any, and the section by its current name
 * while it is on the site, by the one the post kept otherwise.
 *
 * @phpstan-import-type CardPost from PostCardsComponent
 */
final readonly class SitePostCards
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ImageRepository $imageRepository,
        private TermRepository $termRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    /**
     * @param list<PostResponse> $posts
     *
     * @return list<CardPost>
     */
    public function __invoke(array $posts): array
    {
        $images = new ReferenceResolver('image', array_values(array_unique(array_map(static fn (PostResponse $post): string => $post->openingImageId, $posts))), null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
        $sections = [];

        foreach (array_unique(array_map(static fn (PostResponse $post): string => $post->sectionId, $posts)) as $id) {
            $sections[$id] = new TermOnSiteFinder(Taxonomy::Section->value, $id)($this->termRepository)?->name;
        }

        return array_map(fn (PostResponse $post): array => [
            'url' => $this->url($post),
            'title' => $post->title,
            'excerpt' => $post->metaDescription,
            'section' => $sections[$post->sectionId] ?? $post->sectionName,
            'firstPublishedAt' => $post->firstPublishedAt,
            'image' => self::image($images[$post->openingImageId] ?? null),
        ], $posts);
    }

    private function url(PostResponse $post): string
    {
        [$year, $month, $day] = explode('-', $post->day);

        return $this->urlGenerator->generate('web_post', [
            'section' => $post->sectionSlug,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'slug' => $post->slug,
        ]);
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
