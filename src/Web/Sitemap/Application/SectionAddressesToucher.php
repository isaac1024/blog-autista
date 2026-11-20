<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapRepository;

/**
 * Tells the sitemap a section was published or taken off the site: the
 * addresses of its posts name it by its slug there, or by the one each post
 * kept once it is off, so the files that list them changed then.
 */
final readonly class SectionAddressesToucher
{
    public function __construct(
        private string $sectionId,
        private \DateTimeImmutable $moment,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $repository->touchFilesOfSection($this->sectionId, $this->moment);
    }
}
