<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells the page cache in front of the site, Varnish, how long to keep each
 * page it serves, since nothing purges it: a page, an author, a section or a
 * tag a few minutes, so a post just published soon shows in their lists and
 * mosaics; a post, the sitemap and robots.txt an hour. Browsers ask
 * again every time, so they never show a page older than the cache's.
 *
 * Only the pages the site serves as they are; errors and redirects set their
 * own lifetimes.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SiteCacheLifetimes
{
    public const int LISTS = 300;

    public const int POSTS = 3600;

    private const array LIFETIMES = [
        'web_home' => self::LISTS,
        'web_page' => self::LISTS,
        'web_author' => self::LISTS,
        'web_section' => self::LISTS,
        'web_tag' => self::LISTS,
        'web_post' => self::POSTS,
        'web_sitemap_index' => self::POSTS,
        'web_sitemap_file' => self::POSTS,
        'web_robots' => self::POSTS,
    ];

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $route = $event->getRequest()->attributes->get('_route');
        $lifetime = \is_string($route) ? self::LIFETIMES[$route] ?? null : null;

        if (!$event->isMainRequest() || null === $lifetime || 200 !== $response->getStatusCode()) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge($lifetime);
    }
}
