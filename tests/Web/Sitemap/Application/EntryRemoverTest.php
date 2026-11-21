<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap\Application;

use PHPUnit\Framework\TestCase;
use Web\Sitemap\Application\EntryRemover;
use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

final class EntryRemoverTest extends TestCase
{
    private const string PAGE = '01a1e400-0000-7000-8000-000000000001';

    /**
     * An address taken out counts no more in its file, which changed then,
     * and never goes back to an earlier moment.
     */
    public function testAnAddressTakenOutCountsNoMoreInItsFile(): void
    {
        foreach ([
            'later' => ['2026-11-09 10:00:00', '2026-11-09 10:00:00'],
            'earlier than its file' => ['2026-11-05 10:00:00', '2026-11-07 10:00:00'],
        ] as [$moment, $fileMoment]) {
            $entry = self::entry();
            $repository = $this->createMock(SitemapRepository::class);
            $repository->method('entry')->willReturn($entry);
            $repository->expects(self::once())->method('file')->with(SitemapFile::PAGES)->willReturn(new SitemapFile(SitemapFile::PAGES, 2, new \DateTimeImmutable('2026-11-07 10:00:00')));
            $repository->expects(self::once())->method('deleteEntry')->with(self::identicalTo($entry));
            $repository->expects(self::once())->method('saveFile')->with(self::equalTo(new SitemapFile(SitemapFile::PAGES, 1, new \DateTimeImmutable($fileMoment))));
            $repository->expects(self::never())->method('deleteFile');

            new EntryRemover(self::PAGE, new \DateTimeImmutable($moment))($repository);
        }
    }

    /**
     * The last address of a file takes the file with it.
     */
    public function testTheLastAddressOfAFileTakesTheFileWithIt(): void
    {
        $file = new SitemapFile(SitemapFile::PAGES, 1, new \DateTimeImmutable('2026-11-07 10:00:00'));
        $repository = $this->createMock(SitemapRepository::class);
        $repository->method('entry')->willReturn(self::entry());
        $repository->method('file')->willReturn($file);
        $repository->expects(self::once())->method('deleteEntry');
        $repository->expects(self::once())->method('deleteFile')->with(self::identicalTo($file));
        $repository->expects(self::never())->method('saveFile');

        new EntryRemover(self::PAGE, new \DateTimeImmutable('2026-11-09 10:00:00'))($repository);
    }

    /**
     * An address the sitemap hasn't changes nothing; one whose file is gone
     * only goes itself.
     */
    public function testNothingThereChangesNothing(): void
    {
        $none = $this->createMock(SitemapRepository::class);
        $none->method('entry')->willReturn(null);
        $none->expects(self::never())->method('deleteEntry');
        $none->expects(self::never())->method('saveFile');
        new EntryRemover(self::PAGE, new \DateTimeImmutable('2026-11-09 10:00:00'))($none);

        $withoutFile = $this->createMock(SitemapRepository::class);
        $withoutFile->method('entry')->willReturn(self::entry());
        $withoutFile->method('file')->willReturn(null);
        $withoutFile->expects(self::once())->method('deleteEntry');
        $withoutFile->expects(self::never())->method('saveFile');
        $withoutFile->expects(self::never())->method('deleteFile');
        new EntryRemover(self::PAGE, new \DateTimeImmutable('2026-11-09 10:00:00'))($withoutFile);
    }

    private static function entry(): SitemapEntry
    {
        return new SitemapEntry(self::PAGE, SitemapFile::PAGES, '/sobre-mi.html', new \DateTimeImmutable('2026-11-01 10:00:00'), null, [], null);
    }
}
