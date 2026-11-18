<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Tells the sitemap the lists of some sections and tags changed, as when a
 * post of theirs is published or taken off the site: those in the sitemap,
 * and their files, changed then.
 */
final readonly class TermsToucher
{
    /**
     * @param list<string> $termIds
     */
    public function __construct(
        private array $termIds,
        private \DateTimeImmutable $moment,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $repository->touchEntries($this->termIds, $this->moment);
    }
}
