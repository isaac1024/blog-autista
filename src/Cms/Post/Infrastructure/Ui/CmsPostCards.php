<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Ui;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Application\LatestPostsOnSiteFinder;
use Cms\Post\Application\PostOnSiteCardResponse;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Taxonomy\Application\TermOnSiteFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteMedia;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The latest posts on the site of a selection as cards, as a preview paints
 * them, from what the CMS has on the site, gathered at once for all of them:
 * the opening image on the site, or the one its address now leads to, and the
 * section by its name there. Cards lead to the site.
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
    ) {}

    /**
     * @return list<CardPost>
     */
    public function __invoke(PostSelection $selection, int $limit): array
    {
        $posts = new LatestPostsOnSiteFinder($selection, $limit)($this->postRepository);
        $images = new ReferenceResolver('image', array_values(array_unique(array_filter(array_map(static fn (PostOnSiteCardResponse $post): ?string => $post->openingImageId, $posts), static fn (?string $id): bool => null !== $id))), null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
        $sections = [];

        foreach (array_unique(array_map(static fn (PostOnSiteCardResponse $post): string => $post->sectionId, $posts)) as $id) {
            $sections[$id] = new TermOnSiteFinder(Taxonomy::Section, $id)($this->termRepository)?->name;
        }

        return array_map(fn (PostOnSiteCardResponse $post): array => [
            'url' => $this->url($post),
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'section' => $sections[$post->sectionId] ?? '',
            'firstPublishedAt' => $post->firstPublishedAt,
            'image' => self::image(null === $post->openingImageId ? null : $images[$post->openingImageId] ?? null),
        ], $posts);
    }

    private function url(PostOnSiteCardResponse $post): string
    {
        return PostAddressUrl::generate($this->urlGenerator, $post->sectionSlug, $post->day, $post->slug, UrlGeneratorInterface::ABSOLUTE_URL);
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
