<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells a request a page of the site made from one another site made: the
 * browser says so in Sec-Fetch-Site, or, in one too old for it, in Origin.
 */
final readonly class SameOriginRequest
{
    public static function isFromSite(Request $request, string $siteHost): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if (null !== $site) {
            return 'same-origin' === $site;
        }

        $origin = $request->headers->get('Origin');

        return null !== $origin && $siteHost === parse_url($origin, \PHP_URL_HOST);
    }
}
