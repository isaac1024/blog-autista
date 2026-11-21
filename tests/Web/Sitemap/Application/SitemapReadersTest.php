<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap\Application;

use PHPUnit\Framework\TestCase;
use Web\Sitemap\Application\SitemapFileNotFound;
use Web\Sitemap\Application\SitemapFilesFinder;
use Web\Sitemap\Application\SitemapLinesReader;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapLine;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * What the index and the files of the sitemap tell.
 */
final class SitemapReadersTest extends TestCase
{
    public function testTheIndexTellsEachFileWithWhenItLastChanged(): void
    {
        $repository = self::createStub(SitemapRepository::class);
        $repository->method('files')->willReturn([
            new SitemapFile(SitemapFile::PAGES, 2, new \DateTimeImmutable('2026-11-07 10:00:00')),
            new SitemapFile('posts-2026', 9, new \DateTimeImmutable('2026-11-09 10:00:00')),
        ]);

        self::assertEquals([SitemapFile::PAGES => new \DateTimeImmutable('2026-11-07 10:00:00'), 'posts-2026' => new \DateTimeImmutable('2026-11-09 10:00:00')], new SitemapFilesFinder()($repository));
    }

    public function testAFileTellsItsLinesAndOneItDoesNotHaveIsNotThere(): void
    {
        $lines = [new SitemapLine('/sobre-mi.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, null)];
        $repository = self::createStub(SitemapRepository::class);
        $repository->method('file')->willReturnCallback(static fn (string $name): ?SitemapFile => SitemapFile::PAGES === $name ? new SitemapFile($name, 1, new \DateTimeImmutable('2026-11-07 10:00:00')) : null);
        $repository->method('lines')->willReturn($lines);

        self::assertSame($lines, new SitemapLinesReader(SitemapFile::PAGES)($repository));

        $this->expectExceptionObject(SitemapFileNotFound::named('posts-1999'));
        new SitemapLinesReader('posts-1999')($repository);
    }
}
