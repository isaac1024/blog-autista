<?php

declare(strict_types=1);

namespace Web\Sitemap\Application;

use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Puts a post published, or published again, in the sitemap of the year it
 * was first published, unless it asks search engines not to index it. Its
 * section and its tags, those it had and those it has, changed then.
 *
 * It takes what the sitemap needs of the post, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostEntryPlacer
{
    /**
     * @param string       $path    its address, under the slug it kept of its section
     * @param list<string> $termIds its section and its tags
     */
    public function __construct(
        private string $postId,
        private string $sectionId,
        private string $path,
        private \DateTimeImmutable $day,
        private \DateTimeImmutable $lastPublishedAt,
        private string $openingImageId,
        private array $termIds,
        private bool $indexable,
    ) {}

    public function __invoke(SitemapRepository $repository): void
    {
        $had = $repository->entry($this->postId)?->termIds() ?? [];

        if ($this->indexable) {
            new EntryPlacer($this->postId, SitemapFile::postsOf($this->day), $this->path, $this->lastPublishedAt, $this->openingImageId, $this->termIds, $this->sectionId)($repository);
        } else {
            new EntryRemover($this->postId, $this->lastPublishedAt)($repository);
        }

        new TermsToucher([...$had, ...$this->termIds], $this->lastPublishedAt)($repository);
    }
}
