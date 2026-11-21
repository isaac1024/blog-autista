<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap\Application;

use PHPUnit\Framework\TestCase;
use Web\Sitemap\Application\PostEntryPlacer;
use Web\Sitemap\Application\PostEntryRemover;
use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * A post in the sitemap: in the file of the year it was first published,
 * while search engines may index it, and the lists of its section and its
 * tags, those it had and those it has, changed with it.
 */
final class PostEntriesTest extends TestCase
{
    private const string POST = '01a1e700-0000-7000-8000-000000000001';
    private const string SECTION = '01a1e500-0000-7000-8000-000000000001';
    private const string SLEEP = '01a1e500-0000-7000-8000-000000000003';
    private const string SOUND = '01a1e500-0000-7000-8000-000000000004';

    public function testAPostGoesInTheFileOfTheYearItWasFirstPublishedAndTouchesItsTermsOfBeforeAndNow(): void
    {
        $repository = $this->createMock(SitemapRepository::class);
        $repository->method('entry')->willReturn(self::entry([self::SECTION, self::SLEEP]));
        $repository->method('file')->willReturnCallback(static fn (string $name): SitemapFile => new SitemapFile($name, 1, new \DateTimeImmutable('2025-03-02 10:00:00')));
        $repository->expects(self::once())->method('saveEntry')->with(self::equalTo(new SitemapEntry(self::POST, 'posts-2025', '/2025/03/02/un-dia.html', new \DateTimeImmutable('2026-11-09 10:00:00'), 'imagen', [self::SECTION, self::SOUND], self::SECTION)));
        $repository->expects(self::once())->method('touchEntries')->with([self::SECTION, self::SLEEP, self::SECTION, self::SOUND], new \DateTimeImmutable('2026-11-09 10:00:00'));

        self::place($repository, true);
    }

    /**
     * A post that asks search engines not to index it leaves the sitemap,
     * and its terms of before change all the same.
     */
    public function testAPostThatAsksNotToBeIndexedLeavesTheSitemap(): void
    {
        $entry = self::entry([self::SECTION, self::SLEEP]);
        $repository = $this->createMock(SitemapRepository::class);
        $repository->method('entry')->willReturn($entry);
        $repository->method('file')->willReturn(new SitemapFile('posts-2025', 2, new \DateTimeImmutable('2025-03-02 10:00:00')));
        $repository->expects(self::never())->method('saveEntry');
        $repository->expects(self::once())->method('deleteEntry')->with(self::identicalTo($entry));
        $repository->expects(self::once())->method('touchEntries')->with([self::SECTION, self::SLEEP, self::SECTION, self::SOUND], self::anything());

        self::place($repository, false);
    }

    /**
     * A post that leaves the site leaves the sitemap, and the lists of the
     * terms it had change; one never there changes nothing but those of none.
     */
    public function testAPostThatLeavesTheSiteLeavesTheSitemapWithItsTerms(): void
    {
        $repository = $this->createMock(SitemapRepository::class);
        $repository->method('entry')->willReturn(self::entry([self::SECTION, self::SLEEP]));
        $repository->method('file')->willReturn(new SitemapFile('posts-2025', 2, new \DateTimeImmutable('2025-03-02 10:00:00')));
        $repository->expects(self::once())->method('deleteEntry');
        $repository->expects(self::once())->method('touchEntries')->with([self::SECTION, self::SLEEP], new \DateTimeImmutable('2026-11-10 08:00:00'));

        new PostEntryRemover(self::POST, new \DateTimeImmutable('2026-11-10 08:00:00'))($repository);

        $never = $this->createMock(SitemapRepository::class);
        $never->method('entry')->willReturn(null);
        $never->expects(self::never())->method('deleteEntry');
        $never->expects(self::once())->method('touchEntries')->with([], self::anything());

        new PostEntryRemover(self::POST, new \DateTimeImmutable('2026-11-10 08:00:00'))($never);
    }

    private static function place(SitemapRepository $repository, bool $indexable): void
    {
        new PostEntryPlacer(self::POST, self::SECTION, '/2025/03/02/un-dia.html', new \DateTimeImmutable('2025-03-02'), new \DateTimeImmutable('2026-11-09 10:00:00'), 'imagen', [self::SECTION, self::SOUND], $indexable)($repository);
    }

    /**
     * @param list<string> $termIds
     */
    private static function entry(array $termIds): SitemapEntry
    {
        return new SitemapEntry(self::POST, 'posts-2025', '/2025/03/02/un-dia.html', new \DateTimeImmutable('2025-03-02 10:00:00'), 'imagen', $termIds, self::SECTION);
    }
}
