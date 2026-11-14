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
            default => '/'.$name.([] === $parameters ? '' : '?'.http_build_query($parameters)),
        };

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
}
