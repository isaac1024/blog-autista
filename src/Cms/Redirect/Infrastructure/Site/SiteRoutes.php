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
 * the address a page has there.
 */
final readonly class SiteRoutes
{
    /**
     * The route that serves the pages but the home page, by their slugs.
     */
    private const string PAGE_ROUTE = 'web_page';

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
        $slug = $parameters['slug'] ?? null;

        return new SiteRoute(self::PAGE_ROUTE === $route && \is_string($slug) ? $slug : null);
    }

    /**
     * The path the site serves a page at, by its slug.
     */
    public function pagePath(string $slug): string
    {
        return new UrlGenerator($this->router->getRouteCollection(), $this->context())->generate(self::PAGE_ROUTE, ['slug' => $slug]);
    }

    private function context(): RequestContext
    {
        return new RequestContext(method: 'GET', host: $this->webHost);
    }
}
