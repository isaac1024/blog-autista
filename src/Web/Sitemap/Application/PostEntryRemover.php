<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Takes a post off the sitemap once it leaves the site: its section and its
 * tags changed then.
 */
final readonly class PostEntryRemover
{
    public function __construct(
        private string $postId,
        private \DateTimeImmutable $moment,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $had = $repository->entry($this->postId)?->termIds() ?? [];

        new EntryRemover($this->postId, $this->moment)($repository);
        new TermsToucher($had, $this->moment)($repository);
    }
}
