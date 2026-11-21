<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells the page cache in front of the site, Varnish, how long to keep each
 * page it serves, since nothing purges it: a few minutes, so a post just
 * published soon shows in the lists and mosaics, and one corrected or taken
 * off the site soon shows so too. Browsers ask again every time, so they
 * never show a page older than the cache's.
 *
 * Only the pages the site serves as they are; errors and redirects set their
 * own lifetimes.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SiteCacheLifetimes
{
    public const int LIFETIME = 300;

    private const array ROUTES = [
        'web_home',
        'web_page',
        'web_author',
        'web_section',
        'web_tag',
        'web_feed',
        'web_post',
        'web_sitemap_index',
        'web_sitemap_file',
        'web_robots',
    ];

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $route = $event->getRequest()->attributes->get('_route');

        if (!$event->isMainRequest() || !\in_array($route, self::ROUTES, true) || 200 !== $response->getStatusCode()) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(self::LIFETIME);
    }
}
