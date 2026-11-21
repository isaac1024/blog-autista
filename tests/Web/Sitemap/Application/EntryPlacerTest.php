<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap\Application;

use PHPUnit\Framework\TestCase;
use Web\Sitemap\Application\EntryPlacer;
use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

final class EntryPlacerTest extends TestCase
{
    private const string PAGE = '01a1e400-0000-7000-8000-000000000001';

    /**
     * A new address counts in its file, which changed then, and opens the
     * file when it has none yet.
     */
    public function testANewAddressCountsInItsFile(): void
    {
        foreach ([
            'its file' => [new SitemapFile(SitemapFile::PAGES, 2, new \DateTimeImmutable('2026-11-01 10:00:00')), new SitemapFile(SitemapFile::PAGES, 3, new \DateTimeImmutable('2026-11-07 10:00:00'))],
            'a file it opens' => [null, new SitemapFile(SitemapFile::PAGES, 1, new \DateTimeImmutable('2026-11-07 10:00:00'))],
        ] as [$file, $expected]) {
            $repository = $this->createMock(SitemapRepository::class);
            $repository->method('entry')->willReturn(null);
            $repository->method('file')->willReturn($file);
            $repository->expects(self::once())->method('saveEntry')->with(self::equalTo(new SitemapEntry(self::PAGE, SitemapFile::PAGES, '/sobre-mi.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, [], null)));
            $repository->expects(self::once())->method('saveFile')->with(self::equalTo($expected));

            new EntryPlacer(self::PAGE, SitemapFile::PAGES, '/sobre-mi.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, [], null)($repository);
        }
    }

    /**
     * An address already there takes what it is now and counts no more; its
     * file changed then, and never goes back to an earlier moment.
     */
    public function testAnAddressAlreadyThereTakesWhatItIsNowAndCountsNoMore(): void
    {
        foreach ([
            'later' => ['2026-11-09 10:00:00', '2026-11-09 10:00:00'],
            'earlier than its file' => ['2026-11-05 10:00:00', '2026-11-07 10:00:00'],
        ] as [$moment, $fileMoment]) {
            $repository = $this->createMock(SitemapRepository::class);
            $repository->method('entry')->willReturn(new SitemapEntry(self::PAGE, SitemapFile::PAGES, '/antes.html', new \DateTimeImmutable('2026-11-01 10:00:00'), null, [], null));
            $repository->method('file')->willReturn(new SitemapFile(SitemapFile::PAGES, 2, new \DateTimeImmutable('2026-11-07 10:00:00')));
            $repository->expects(self::once())->method('saveEntry')->with(self::equalTo(new SitemapEntry(self::PAGE, SitemapFile::PAGES, '/sobre-mi.html', new \DateTimeImmutable($moment), null, [], null)));
            $repository->expects(self::once())->method('saveFile')->with(self::equalTo(new SitemapFile(SitemapFile::PAGES, 2, new \DateTimeImmutable($fileMoment))));

            new EntryPlacer(self::PAGE, SitemapFile::PAGES, '/sobre-mi.html', new \DateTimeImmutable($moment), null, [], null)($repository);
        }
    }

    /**
     * What an address is never moves to another file: an entry keeps the
     * file it was placed in.
     */
    public function testAnAddressNeverMovesToAnotherFile(): void
    {
        $repository = $this->createMock(SitemapRepository::class);
        $repository->method('entry')->willReturn(new SitemapEntry(self::PAGE, 'posts-2025', '/a/2025/03/02/un-dia.html', new \DateTimeImmutable('2025-03-02 10:00:00'), null, [], null));
        $repository->method('file')->willReturnCallback(static fn (string $name): SitemapFile => new SitemapFile($name, 1, new \DateTimeImmutable('2025-03-02 10:00:00')));
        $repository->expects(self::once())->method('saveEntry')->with(self::callback(static fn (SitemapEntry $entry): bool => 'posts-2025' === $entry->file()));

        new EntryPlacer(self::PAGE, 'posts-2026', '/2025/03/02/un-dia.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, [], null)($repository);
    }
}
