<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Generates every URL as the name of its route and its parameters, so a test
 * tells which route a page leads to; the pages of the site, at the root and
 * at their slug, are shaped as the site serves them. An absolute URL is on
 * the site.
 */
final class RouteNameUrlGenerator implements UrlGeneratorInterface
{
    public const string SITE = 'https://www.blogautista.localhost';

    private RequestContext $context;

    public function __construct()
    {
        $this->context = new RequestContext();
    }

    /**
     * @param array<mixed> $parameters
     */
    #[\Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $slug = $parameters['slug'] ?? null;
        $path = match (true) {
            'web_home' === $name => '/',
            'web_page' === $name && \is_string($slug) => '/'.$slug.'.html',
            'web_section' === $name && \is_string($slug) => '/'.$slug.'/',
            'web_tag' === $name && \is_string($slug) => '/tag/'.$slug.'.html',
            'web_author' === $name && \is_string($slug) => '/autor/'.$slug.'.html',
            'web_post' === $name && \is_string($slug) => \sprintf('/%s/%s/%s/%s/%s.html', self::text($parameters, 'section'), self::text($parameters, 'year'), self::text($parameters, 'month'), self::text($parameters, 'day'), $slug),
            default => '/'.$name.([] === $parameters ? '' : '?'.http_build_query($parameters)),
        };

        // What the path of a route of the site doesn't take goes in its query, as the router does.
        $query = str_starts_with($path, '/'.$name) ? [] : array_diff_key($parameters, array_flip(['slug', 'section', 'year', 'month', 'day']));
        $path .= [] === $query ? '' : '?'.http_build_query($query);

        return self::ABSOLUTE_URL === $referenceType ? self::SITE.$path : $path;
    }

    #[\Override]
    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    #[\Override]
    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * @param array<mixed> $parameters
     */
    private static function text(array $parameters, string $name): string
    {
        $value = $parameters[$name] ?? '';

        return \is_string($value) ? $value : '';
    }
}
