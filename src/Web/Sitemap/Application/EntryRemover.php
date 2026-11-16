<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Takes an address out of the sitemap, as when what it is leaves the site or
 * asks search engines not to index it: its file changed then, and goes once
 * it has no entry left.
 */
final readonly class EntryRemover
{
    public function __construct(
        private string $resourceId,
        private \DateTimeImmutable $moment,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $entry = $repository->entry($this->resourceId);

        if (null === $entry) {
            return;
        }

        $file = $repository->file($entry->file());
        $repository->deleteEntry($entry);

        if (null === $file) {
            return;
        }

        $file->removed($this->moment);

        if ($file->isEmpty()) {
            $repository->deleteFile($file);

            return;
        }

        $repository->saveFile($file);
    }
}
