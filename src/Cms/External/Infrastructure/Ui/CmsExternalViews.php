<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Ui;

use Cms\External\Application\ExternalResourcesOnSiteFinder;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Infrastructure\Controller\ExternalImageUrl;
use Cms\Page\Application\CookiePolicyOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Clock;
use Shared\Domain\External\ImageShape;
use Shared\Infrastructure\Ui\ExternalComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a preview of the CMS paints of the external resources a text links
 * to or shows, as the site would: those published and available, each with
 * its address and its title, and all its widget paints, its image served by
 * the CMS behind a link signed for a while, and the cookie policy of the
 * site, keyed by id.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 */
final readonly class CmsExternalViews
{
    /**
     * How long the link to an image lets anyone with the preview see it, as
     * long as a shared preview lasts.
     */
    private const string IMAGE_VALIDITY = 'P7D';

    public function __construct(
        private ExternalResourceRepository $resourceRepository,
        private PageRepository $pageRepository,
        private UrlGeneratorInterface $urlGenerator,
        private ExternalImageUrl $imageUrl,
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

        $policy = new CookiePolicyOnSiteFinder()($this->pageRepository);
        $cookies = null === $policy?->slug ? null : $this->urlGenerator->generate('web_page', ['slug' => $policy->slug], UrlGeneratorInterface::ABSOLUTE_URL);
        $expiresAt = Clock::now()->add(new \DateInterval(self::IMAGE_VALIDITY));
        $views = [];

        foreach ($resources as $id => $resource) {
            $shape = null === $resource->imageShape ? null : ImageShape::from($resource->imageShape);
            $src = null === $resource->image ? null : ($this->imageUrl)($id, $resource->image, $expiresAt);

            $views[$id] = ['url' => $resource->url, 'title' => $resource->title, 'external' => [
                'kind' => $resource->kind,
                'provider' => $resource->provider,
                'locator' => $resource->locator,
                'url' => $resource->url,
                'title' => $resource->title,
                'summary' => $resource->summary,
                'language' => $resource->language,
                'details' => $resource->details,
                'image' => null === $src || null === $shape ? null : ['src' => $src, 'srcset' => \sprintf('%s %dw', $src, $shape->width()), 'width' => $shape->width(), 'height' => $shape->height()],
                'cookies' => $cookies,
            ]];
        }

        return $views;
    }
}
