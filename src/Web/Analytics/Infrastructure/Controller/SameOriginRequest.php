<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells a request a page of the same origin made, scheme, host and port,
 * from one any other made: the browser says so in Sec-Fetch-Site, or, in one
 * too old for it, in Origin.
 *
 * Only a browser is held to it: any other client can say what it likes.
 */
final readonly class SameOriginRequest
{
    public static function isFromSite(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if (null !== $site) {
            return 'same-origin' === $site;
        }

        return $request->getSchemeAndHttpHost() === $request->headers->get('Origin');
    }
}
