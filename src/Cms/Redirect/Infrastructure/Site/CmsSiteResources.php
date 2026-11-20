<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Site;

use Cms\Author\Application\AuthorAtAddressFinder;
use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageAtPathFinder;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageAtAddressFinder;
use Cms\Page\Application\PageLastAddressFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostAddressesFinder;
use Cms\Post\Application\PostAddressResponse;
use Cms\Post\Application\PostAtAddressFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Taxonomy\Application\TermAtAddressFinder;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\PostPath;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the pages, the images, the posts, the sections
 * and tags, and the authors of the CMS what the site has, and the routes of
 * the site which addresses it answers itself.
 *
 * It reaches every module a redirect can lead to, so its coupling, its
 * complexity and what it takes grow with the kinds of resource and say
 * nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
#[AsAlias(SiteResources::class)]
final readonly class CmsSiteResources implements SiteResources
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private Classifiers $classifiers,
        private SiteRoutes $siteRoutes,
    ) {}

    #[\Override]
    public function isOnSite(Resource $resource): bool
    {
        return match ($resource->type()) {
            ResourceType::Page => true === (new PagesFinder([$resource->id()])($this->pageRepository)[0] ?? null)?->isPublished(),
            ResourceType::Image => true === (new ImagesFinder([$resource->id()])($this->imageRepository)[0] ?? null)?->isPublished(),
            ResourceType::Post => true === (new PostsFinder([$resource->id()])($this->postRepository)[0] ?? null)?->isPublished(),
            ResourceType::Term => true === $this->term($resource)?->isPublished(),
            ResourceType::Author => true === $this->author($resource)?->isPublished(),
        };
    }

    #[\Override]
    public function isLive(Source $source): bool
    {
        $imagePath = $source->imagePath();

        if (null !== $imagePath) {
            return true === $this->imageAt($imagePath)?->isPublished();
        }

        $post = $this->postAt($source);

        if (null !== $post) {
            return null !== $post->onSite($this->postRepository);
        }

        $route = $this->siteRoutes->match($source->value());

        if (null === $route) {
            return false;
        }

        // A resource answers its address only while it is on the site; any
        // other address of the site always answers.
        return match ($route->kind) {
            SiteRoute::PAGE => null !== new PageAtAddressFinder($route->parameter('slug'))->onSite($this->pageRepository),
            SiteRoute::SECTION, SiteRoute::TAG => null !== new TermAtAddressFinder($route->kind, $route->parameter('slug'))->onSite($this->termRepository),
            SiteRoute::AUTHOR => null !== new AuthorAtAddressFinder($route->parameter('slug'))->onSite($this->authorRepository),
            default => true,
        };
    }

    #[\Override]
    public function addressOf(Resource $resource): ?Source
    {
        return match ($resource->type()) {
            ResourceType::Image => $this->imageAddress($resource, false),
            ResourceType::Page => $this->lastPageAddress($resource),
            ResourceType::Post => $this->postAddress($this->postAddresses($resource, false)),
            ResourceType::Term => $this->termAddress($this->term($resource), false),
            ResourceType::Author => $this->authorAddress($this->author($resource)?->lastSiteSlug),
        };
    }

    #[\Override]
    public function nextAddressOf(Resource $resource): ?Source
    {
        return match ($resource->type()) {
            // The file of an image never changes, nor does its address.
            ResourceType::Image => $this->imageAddress($resource, true),
            ResourceType::Page => $this->pageAddress((new PagesFinder([$resource->id()])($this->pageRepository)[0] ?? null)?->slug),
            ResourceType::Post => $this->postAddress($this->postAddresses($resource, true)),
            ResourceType::Term => $this->termAddress($this->term($resource), true),
            ResourceType::Author => $this->authorAddress($this->author($resource)?->slug),
        };
    }

    #[\Override]
    public function lastAt(Source $source): ?Resource
    {
        $imagePath = $source->imagePath();

        if (null !== $imagePath) {
            $image = $this->imageAt($imagePath);

            return true === $image?->isUnpublished() ? Resource::image($image->id) : null;
        }

        $post = $this->postAt($source);

        if (null !== $post) {
            return self::resourceOf(ResourceType::Post, $post->lastOffSite($this->postRepository)?->id);
        }

        $route = $this->siteRoutes->match($source->value());

        return match ($route?->kind) {
            SiteRoute::PAGE => self::resourceOf(ResourceType::Page, new PageAtAddressFinder($route->parameter('slug'))->lastOffSite($this->pageRepository)?->id),
            SiteRoute::SECTION, SiteRoute::TAG => self::resourceOf(ResourceType::Term, new TermAtAddressFinder($route->kind, $route->parameter('slug'))->lastOffSite($this->termRepository)?->id),
            SiteRoute::AUTHOR => self::resourceOf(ResourceType::Author, new AuthorAtAddressFinder($route->parameter('slug'))->lastOffSite($this->authorRepository)?->id),
            default => null,
        };
    }

    private static function resourceOf(ResourceType $type, ?string $id): ?Resource
    {
        return null === $id ? null : new Resource($type, $id);
    }

    private function imageAt(string $path): ?ImageResponse
    {
        return new ImageAtPathFinder($path)($this->imageRepository);
    }

    /**
     * An image draft was never on the site, so it has no address yet, but
     * will have one once published.
     */
    private function imageAddress(Resource $resource, bool $next): ?Source
    {
        $image = new ImagesFinder([$resource->id()])($this->imageRepository)[0] ?? null;

        return null === $image || (!$next && $image->isDraft()) ? null : Source::ofImage($image->path);
    }

    private function lastPageAddress(Resource $resource): ?Source
    {
        try {
            return $this->pageAddress(new PageLastAddressFinder($resource->id())($this->pageRepository));
        } catch (PageNotFound) {
            return null;
        }
    }

    private function pageAddress(?string $slug): ?Source
    {
        return null === $slug ? null : Source::fromPath($this->siteRoutes->pagePath($slug));
    }

    /**
     * The post an address names, by its day and slug: the address of a post
     * is kept without its section. Null when it is no address of a post.
     */
    private function postAt(Source $source): ?PostAtAddressFinder
    {
        $address = PostPath::dayAndSlug($source->value());

        return null === $address ? null : new PostAtAddressFinder($address['day'], $address['slug']);
    }

    /**
     * The address a post has on the site, or last had there, or the one it
     * would have once published.
     */
    private function postAddresses(Resource $resource, bool $next): ?PostAddressResponse
    {
        $addresses = new PostAddressesFinder($resource->id());

        try {
            return $next ? $addresses->next($this->postRepository, $this->classifiers) : $addresses->last($this->postRepository);
        } catch (PostNotFound) {
            return null;
        }
    }

    private function postAddress(?PostAddressResponse $address): ?Source
    {
        return null === $address ? null : Source::fromPath(PostPath::fromDayAndSlug($address->day, $address->slug));
    }

    private function term(Resource $resource): ?TermResponse
    {
        return new TermsFinder([$resource->id()])($this->termRepository)[0] ?? null;
    }

    /**
     * The address a section or a tag has on the site, or last had there, or
     * the one it would have once published.
     */
    private function termAddress(?TermResponse $term, bool $next): ?Source
    {
        $slug = $next ? $term?->slug : $term?->lastSiteSlug;

        return null === $term || null === $slug ? null : Source::fromPath($this->siteRoutes->termPath($term->taxonomy, $slug));
    }

    private function author(Resource $resource): ?AuthorResponse
    {
        return new AuthorsFinder([$resource->id()])($this->authorRepository)[0] ?? null;
    }

    private function authorAddress(?string $slug): ?Source
    {
        return null === $slug ? null : Source::fromPath($this->siteRoutes->authorPath($slug));
    }
}
