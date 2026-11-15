<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets a page frame the resources it embeds, such as a video of YouTube,
 * from their origins alone, and only on that page: the policy of the site
 * frames nothing else. Origins differ from page to page, as a post of
 * Mastodon is framed from its own server, so each page tells its own, and
 * a cache keeps the policy with the page it came with.
 *
 * It runs after the policy is written, adding the origins to its frame-src.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -16)]
final readonly class FrameSources
{
    private const string ATTRIBUTE = '_frame_sources';

    private const string ORIGIN = '#^https://[a-z0-9-]+(\.[a-z0-9-]+)+$#';

    public function __invoke(ResponseEvent $event): void
    {
        $origins = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        $policy = $event->getResponse()->headers->get('Content-Security-Policy');

        if (!$event->isMainRequest() || !\is_array($origins) || null === $policy) {
            return;
        }

        // Only plain origins, so nothing the page says widens the policy otherwise.
        $origins = array_filter($origins, static fn (mixed $origin): bool => \is_string($origin) && 1 === preg_match(self::ORIGIN, $origin));

        if ([] === $origins) {
            return;
        }

        $directives = array_map('trim', explode(';', $policy));
        $found = false;

        foreach ($directives as $index => $directive) {
            if (str_starts_with($directive, 'frame-src ')) {
                $directives[$index] = $directive.' '.implode(' ', $origins);
                $found = true;
            }
        }

        if (!$found) {
            $directives[] = 'frame-src '.implode(' ', $origins);
        }

        $event->getResponse()->headers->set('Content-Security-Policy', implode('; ', array_filter($directives, static fn (string $directive): bool => '' !== $directive)));
    }

    /**
     * Lets the page of the request frame from the origins, such as
     * https://www.youtube-nocookie.com.
     *
     * @param list<string> $origins
     */
    public static function allow(Request $request, array $origins): void
    {
        $allowed = $request->attributes->get(self::ATTRIBUTE);
        $allowed = \is_array($allowed) ? array_filter($allowed, \is_string(...)) : [];
        $request->attributes->set(self::ATTRIBUTE, array_values(array_unique([...$allowed, ...$origins])));
    }
}
