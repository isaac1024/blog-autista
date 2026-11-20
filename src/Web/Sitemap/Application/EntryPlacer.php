<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Puts in the sitemap an address search engines may index, in the file it
 * belongs to, or brings the one it has up to date: what it is never moves
 * to another file, as a page, a section, a tag or an author keeps its kind,
 * and a post the year it was first published. Its file changed then.
 *
 * It takes what an entry has, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class EntryPlacer
{
    /**
     * @param list<string> $termIds   the section and the tags of a post, none for anything else
     * @param ?string      $sectionId the section of a post, whose slug on the site its address names; none for anything else
     */
    public function __construct(
        private string $resourceId,
        private string $file,
        private string $path,
        private \DateTimeImmutable $lastmod,
        private ?string $imageId,
        private array $termIds,
        private ?string $sectionId,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $entry = $repository->entry($this->resourceId);
        $file = $repository->file($this->file) ?? SitemapFile::open($this->file, $this->lastmod);

        if (null === $entry) {
            $entry = new SitemapEntry($this->resourceId, $this->file, $this->path, $this->lastmod, $this->imageId, $this->termIds, $this->sectionId);
            $file->added($this->lastmod);
        } else {
            $entry->update($this->path, $this->lastmod, $this->imageId, $this->termIds, $this->sectionId);
            $file->touched($this->lastmod);
        }

        $repository->saveEntry($entry);
        $repository->saveFile($file);
    }
}
