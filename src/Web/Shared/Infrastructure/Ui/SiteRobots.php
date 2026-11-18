<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

/**
 * What a page of the site tells search engines in its robots meta tag:
 * whether they may list it and follow its links, as its content asks, and,
 * on every page they may list, that they may show its images large, in
 * results and in Discover.
 */
final class SiteRobots
{
    /**
     * @param string $robots one of the Robots values, such as "index,follow"
     */
    public static function meta(string $robots): string
    {
        return str_starts_with($robots, 'index,') ? $robots.',max-image-preview:large' : $robots;
    }
}
