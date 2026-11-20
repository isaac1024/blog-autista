<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Site;

/**
 * A route of the site that answers an address. When it is the route of a
 * kind of resource, such as the pages or the posts, it only answers while
 * the resource it names is on the site, so it says which: its kind and the
 * parameters of the address, such as the slug. Any other route, such as the
 * home page, always answers.
 */
final readonly class SiteRoute
{
    public const string PAGE = 'page';
    public const string SECTION = 'section';
    public const string TAG = 'tag';
    public const string AUTHOR = 'author';

    /**
     * @param ?string               $kind       one of the constants, null for a route that always answers
     * @param array<string, string> $parameters of the address, by their names in the route
     */
    public function __construct(
        public ?string $kind,
        public array $parameters,
    ) {}

    /**
     * A parameter of the address, empty when it has none of the name.
     */
    public function parameter(string $name): string
    {
        return $this->parameters[$name] ?? '';
    }
}
