<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Outbound port to the cache of pages in front of the site, which keeps each
 * page as long as it tells: one taken off the site, or corrected, is
 * forgotten at once rather than shown until it expires.
 */
interface PageCache
{
    /**
     * Forgets what the cache keeps of an address of the site, such as
     * /mi-pagina.html; nothing happens when it keeps nothing there.
     */
    public function forget(string $path): void;
}
