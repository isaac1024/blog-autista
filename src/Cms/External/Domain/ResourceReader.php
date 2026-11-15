<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * Outbound port that reads external resources where they are, or asks their
 * providers: what one is and what it tells of itself, and whether its
 * address still leads to it.
 */
interface ResourceReader
{
    /**
     * What the resource at the address is and tells. It never fails: a
     * resource that can't be reached is read from its address alone.
     */
    public function read(ResourceUrl $url): Reading;

    /**
     * Whether the address still leads to the resource of the kind, by the
     * provider named, if any, under its locator.
     */
    public function check(ResourceUrl $url, ResourceKind $kind, ?string $provider, ?string $locator): CheckOutcome;
}
