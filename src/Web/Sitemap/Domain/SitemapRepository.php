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
     * The entries of the posts that open with the image changed at the
     * moment given, and so did their files. Each keeps when it last changed,
     * the latest of the two: it never moves back.
     */
    public function touchEntriesWithImage(string $imageId, \DateTimeImmutable $moment): void;

    /**
     * Moves the files that list posts of the section to the moment: the
     * addresses of those posts name the section by its slug on the site,
     * which changed. The entries keep their own dates, their pages unchanged.
     */
    public function touchFilesOfSection(string $sectionId, \DateTimeImmutable $moment): void;

    /**
     * The entries of the resources given changed at the moment, as the lists
     * of a section or a tag do, and so did their files; each keeps the
     * latest of the two. A resource with no entry changes nothing.
     *
     * @param list<string> $resourceIds
     */
    public function touchEntries(array $resourceIds, \DateTimeImmutable $moment): void;

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
     * only while it lists some post, and the image of a post, by its id, and
     * where its copies are while it is on the site.
     *
     * @return iterable<SitemapLine>
     */
    public function lines(string $file): iterable;

    /**
     * The images the posts of a file open with that are off the site, each
     * once, which the file names as the ones their addresses lead to.
     *
     * @return list<string>
     */
    public function imagesOffSite(string $file): array;
}
