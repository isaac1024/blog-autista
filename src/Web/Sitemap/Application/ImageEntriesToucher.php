<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Tells the sitemap an image changed, as when it is put on the site or taken
 * off it: the posts that open with it, and their files, changed then, so
 * search engines fetch them again and learn of the image they name now.
 */
final readonly class ImageEntriesToucher
{
    public function __construct(
        private string $imageId,
        private \DateTimeImmutable $moment,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        foreach ($repository->entriesWithImage($this->imageId) as $entry) {
            $entry->touch($this->moment);
            $repository->saveEntry($entry);
            $file = $repository->file($entry->file());

            if (null !== $file) {
                $file->touched($this->moment);
                $repository->saveFile($file);
            }
        }
    }
}
