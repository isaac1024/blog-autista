<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Which route of the site, if any, answers an address of it, as a browser
 * would ask for it on the site's host, whichever host the CMS is on, and
 * the address each kind of resource has there.
 */
final readonly class SiteRoutes
{
    /**
     * The routes that serve a kind of resource each, by the name of their
     * route: the pages but the home page, the sections, the tags, the
     * authors and the posts.
     */
    private const array ROUTES = [
        'web_page' => SiteRoute::PAGE,
        'web_section' => SiteRoute::SECTION,
        'web_tag' => SiteRoute::TAG,
        'web_author' => SiteRoute::AUTHOR,
        'web_post' => SiteRoute::POST,
    ];

    public function __construct(
        private RouterInterface $router,
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
    ) {}

    public function match(string $path): ?SiteRoute
    {
        $matcher = new UrlMatcher($this->router->getRouteCollection(), $this->context());

        try {
            $parameters = $matcher->match($path);
        } catch (ExceptionInterface) {
            return null;
        }

        $route = \is_string($parameters['_route'] ?? null) ? $parameters['_route'] : '';
        $address = [];

        // Those of the address, not those the router adds, such as the route.
        foreach ($parameters as $name => $value) {
            if (\is_string($name) && \is_string($value) && !str_starts_with($name, '_')) {
                $address[$name] = $value;
            }
        }

        return new SiteRoute(self::ROUTES[$route] ?? null, $address);
    }

    /**
     * The path the site serves a page at, by its slug.
     */
    public function pagePath(string $slug): string
    {
        return $this->path('web_page', ['slug' => $slug]);
    }

    /**
     * The path the site serves a section or a tag at, by its slug.
     */
    public function termPath(string $kind, string $slug): string
    {
        return $this->path(SiteRoute::SECTION === $kind ? 'web_section' : 'web_tag', ['slug' => $slug]);
    }

    /**
     * The path the site serves an author at, by its slug.
     */
    public function authorPath(string $slug): string
    {
        return $this->path('web_author', ['slug' => $slug]);
    }

    /**
     * The path the site serves a post at, by the slug of its section, the
     * day it was first published, as Y-m-d, and its own slug.
     */
    public function postPath(string $section, string $day, string $slug): string
    {
        [$year, $month, $date] = explode('-', $day);

        return $this->path('web_post', ['section' => $section, 'year' => $year, 'month' => $month, 'day' => $date, 'slug' => $slug]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function path(string $route, array $parameters): string
    {
        return new UrlGenerator($this->router->getRouteCollection(), $this->context())->generate($route, $parameters);
    }

    private function context(): RequestContext
    {
        return new RequestContext(method: 'GET', host: $this->webHost);
    }
}
