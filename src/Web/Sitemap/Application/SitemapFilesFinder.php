<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Finds the files of the sitemap, by name, with when each last changed, as
 * its index lists them.
 */
final readonly class SitemapFilesFinder
{
    /**
     * @return array<string, \DateTimeImmutable> the moment each last changed, by name
     */
    public function __invoke(SitemapRepository $repository): array
    {
        $files = [];

        foreach ($repository->files() as $file) {
            $files[$file->name()] = $file->lastmod();
        }

        return $files;
    }
}
