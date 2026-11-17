<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Domain\External\ImageShape;
use Shared\Domain\ExternalImageCopy;
use Shared\Infrastructure\Ui\ExternalComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\External\Application\ExternalResourcesOnSiteFinder;
use Web\External\Domain\ExternalResourceRepository;
use Web\Page\Application\CookiePolicyFinder;
use Web\Page\Domain\PageRepository;

/**
 * What the site paints of the external resources a text links to or shows:
 * those on the site and available, each with its address and its title, and
 * all its widget paints, its image as the copies the site serves among it,
 * and its cookie policy, keyed by id.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 */
final readonly class SiteExternalViews
{
    public function __construct(
        private ExternalResourceRepository $resourceRepository,
        private PageRepository $pageRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{url: string, title: string, external: ExternalView}>
     */
    public function __invoke(array $ids): array
    {
        $resources = [] === $ids ? [] : new ExternalResourcesOnSiteFinder($ids)($this->resourceRepository);

        if ([] === $resources) {
            return [];
        }

        $policy = new CookiePolicyFinder()($this->pageRepository);
        $cookies = null === $policy?->slug ? null : $this->urlGenerator->generate('web_page', ['slug' => $policy->slug]);
        $views = [];

        foreach ($resources as $id => $resource) {
            $views[$id] = ['url' => $resource->url, 'title' => $resource->title, 'external' => [
                'kind' => $resource->kind,
                'provider' => $resource->provider,
                'locator' => $resource->locator,
                'url' => $resource->url,
                'title' => $resource->title,
                'summary' => $resource->summary,
                'language' => $resource->language,
                'details' => $resource->details,
                'image' => null === $resource->image || null === $resource->imageShape ? null : self::image($resource->image, ImageShape::from($resource->imageShape)),
                'cookies' => $cookies,
            ]];
        }

        return $views;
    }

    /**
     * The copies of the image the site serves, the largest as its source.
     *
     * @return array{src: string, srcset: string, width: int, height: int}
     */
    private static function image(string $path, ImageShape $shape): array
    {
        $copies = array_map(static fn (int $width): ExternalImageCopy => new ExternalImageCopy($path, $shape, $width), $shape->widths());
        $largest = $copies[array_key_last($copies)];

        return [
            'src' => $largest->url(),
            'srcset' => implode(', ', array_map(static fn (ExternalImageCopy $copy): string => \sprintf('%s %dw', $copy->url(), $copy->width()), $copies)),
            'width' => $largest->width(),
            'height' => $largest->height(),
        ];
    }
}
