<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

/**
 * Outbound port that keeps the sitemap of the site: its entries and its
 * files, and the lines each file tells.
 */
interface SitemapRepository
{
    public function entry(string $resourceId): ?SitemapEntry;

    /**
     * @param list<string> $resourceIds
     *
     * @return list<SitemapEntry>
     */
    public function entries(array $resourceIds): array;

    public function saveEntry(SitemapEntry $entry): void;

    public function deleteEntry(SitemapEntry $entry): void;

    public function file(string $name): ?SitemapFile;

    public function saveFile(SitemapFile $file): void;

    public function deleteFile(SitemapFile $file): void;

    /**
     * Every file with entries, by name.
     *
     * @return list<SitemapFile>
     */
    public function files(): array;

    /**
     * The lines of a file, by path, read one at a time: a section or a tag
     * only while it lists some post, and the image of a post only while it
     * is on the site.
     *
     * @return iterable<SitemapLine>
     */
    public function lines(string $file): iterable;
}
