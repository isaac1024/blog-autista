<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapLine;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Reads the lines of a file of the sitemap, one at a time, as it is written
 * out; a file it doesn't have isn't there.
 */
final readonly class SitemapLinesReader
{
    public function __construct(private string $name) {}

    /**
     * @return iterable<SitemapLine>
     */
    public function __invoke(SitemapRepository $repository): iterable
    {
        if (null === $repository->file($this->name)) {
            throw SitemapFileNotFound::named($this->name);
        }

        return $repository->lines($this->name);
    }
}
