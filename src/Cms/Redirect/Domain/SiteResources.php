<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * Outbound port that tells a redirect what the site has: which resources are
 * on it, which addresses already answer, and which resource off it last had
 * an address. It answers from the CMS as it is now, not from the site, which
 * only catches up later.
 */
interface SiteResources
{
    /**
     * Whether the resource is published.
     */
    public function isOnSite(Resource $resource): bool;

    /**
     * Whether the site already answers the address with something of its
     * own, such as a published page or a fixed address like the home page,
     * so a redirect there would never be followed.
     */
    public function isLive(Source $source): bool;

    /**
     * The address the resource has on the site, or last had there once off
     * it; null when it has none, such as a page never published, or one
     * whose last address another took.
     */
    public function addressOf(Resource $resource): ?Source;

    /**
     * The resource off the site whose last address it is, or null when none
     * is: an address never had one, or another resource took it since.
     */
    public function lastAt(Source $source): ?Resource;
}
