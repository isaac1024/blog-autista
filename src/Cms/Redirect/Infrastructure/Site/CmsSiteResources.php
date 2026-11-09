<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Site;

use Cms\Image\Application\ImageAtPathFinder;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStatus;
use Cms\Page\Application\PageAtAddressFinder;
use Cms\Page\Application\PageLastAddressFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageStatus;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the pages and the images of the CMS what the
 * site has, and the routes of the site which addresses it answers itself.
 *
 * It reaches every module a redirect can lead to, so its coupling grows with
 * the kinds of resource and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[AsAlias(SiteResources::class)]
final readonly class CmsSiteResources implements SiteResources
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private SiteRoutes $siteRoutes,
    ) {}

    #[\Override]
    public function isOnSite(Resource $resource): bool
    {
        return match ($resource->type()) {
            ResourceType::Page => PageStatus::Published->value === (new PagesFinder([$resource->id()])($this->pageRepository)[0] ?? null)?->status,
            ResourceType::Image => ImageStatus::Published->value === (new ImagesFinder([$resource->id()])($this->imageRepository)[0] ?? null)?->status,
        };
    }

    #[\Override]
    public function isLive(Source $source): bool
    {
        $imagePath = $source->imagePath();

        if (null !== $imagePath) {
            return ImageStatus::Published->value === $this->imageAt($imagePath)?->status;
        }

        $route = $this->siteRoutes->match($source->value());

        if (null === $route) {
            return false;
        }

        // A page answers its address only while it is on the site; any other
        // address of the site always answers.
        return null === $route->pageSlug || null !== new PageAtAddressFinder($route->pageSlug)->onSite($this->pageRepository);
    }

    #[\Override]
    public function addressOf(Resource $resource): ?Source
    {
        if (ResourceType::Image === $resource->type()) {
            $image = new ImagesFinder([$resource->id()])($this->imageRepository)[0] ?? null;

            // An image draft was never on the site.
            return null === $image || ImageStatus::Draft->value === $image->status ? null : Source::ofImage($image->path);
        }

        try {
            $slug = new PageLastAddressFinder($resource->id())($this->pageRepository);
        } catch (PageNotFound) {
            return null;
        }

        return null === $slug ? null : Source::fromPath($this->siteRoutes->pagePath($slug));
    }

    #[\Override]
    public function lastAt(Source $source): ?Resource
    {
        $imagePath = $source->imagePath();

        if (null !== $imagePath) {
            $image = $this->imageAt($imagePath);

            return ImageStatus::Unpublished->value === $image?->status ? Resource::image($image->id) : null;
        }

        $slug = $this->siteRoutes->match($source->value())?->pageSlug;
        $page = null === $slug ? null : new PageAtAddressFinder($slug)->lastOffSite($this->pageRepository);

        return null === $page ? null : Resource::page($page->id);
    }

    private function imageAt(string $path): ?ImageResponse
    {
        return new ImageAtPathFinder($path)($this->imageRepository);
    }
}
