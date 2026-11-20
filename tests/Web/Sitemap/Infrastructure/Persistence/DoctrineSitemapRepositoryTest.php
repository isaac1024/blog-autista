<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * The entries of the sitemap, and their files, move to when they changed
 * in two statements however many they are: those that match and no other,
 * and never back.
 */
final class DoctrineSitemapRepositoryTest extends KernelTestCase
{
    private const string IMAGE = '01a1e800-0000-7000-8000-000000000001';

    private const string OTHER_IMAGE = '01a1e800-0000-7000-8000-000000000002';

    private const string POST = '01a1e700-0000-7000-8000-000000000001';

    private const string OLD_POST = '01a1e700-0000-7000-8000-000000000002';

    private const string NEWER_POST = '01a1e700-0000-7000-8000-000000000003';

    private const string OTHER_POST = '01a1e700-0000-7000-8000-000000000004';

    private const string TAG = '01a1e500-0000-7000-8000-000000000001';

    private const string OTHER_TAG = '01a1e500-0000-7000-8000-000000000002';

    #[\Override]
    protected function setUp(): void
    {
        $repository = $this->repository();
        $repository->saveFile(new SitemapFile('posts-2026', 2, new \DateTimeImmutable('2026-11-07 10:00:00')));
        $repository->saveFile(new SitemapFile('posts-2025', 1, new \DateTimeImmutable('2025-03-02 10:00:00')));
        $repository->saveFile(new SitemapFile('posts-2024', 2, new \DateTimeImmutable('2026-12-01 10:00:00')));
        $repository->saveFile(new SitemapFile(SitemapFile::TAGS, 2, new \DateTimeImmutable('2026-11-07 10:00:00')));
        $repository->saveEntry(new SitemapEntry(self::POST, 'posts-2026', '/a.html', new \DateTimeImmutable('2026-11-07 10:00:00'), self::IMAGE, [], null));
        $repository->saveEntry(new SitemapEntry(self::OTHER_POST, 'posts-2026', '/b.html', new \DateTimeImmutable('2026-11-07 10:00:00'), self::OTHER_IMAGE, [], null));
        $repository->saveEntry(new SitemapEntry(self::OLD_POST, 'posts-2025', '/c.html', new \DateTimeImmutable('2025-03-02 10:00:00'), self::IMAGE, [], null));
        // Changed after the moment it is touched at, with its file.
        $repository->saveEntry(new SitemapEntry(self::NEWER_POST, 'posts-2024', '/d.html', new \DateTimeImmutable('2026-12-01 10:00:00'), self::IMAGE, [], null));
        $repository->saveEntry(new SitemapEntry(self::TAG, SitemapFile::TAGS, '/tag/a.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, [], null));
        $repository->saveEntry(new SitemapEntry(self::OTHER_TAG, SitemapFile::TAGS, '/tag/b.html', new \DateTimeImmutable('2026-11-07 10:00:00'), null, [], null));
    }

    public function testItMovesTheEntriesOfAnImageAndTheirFilesNeverBack(): void
    {
        $this->repository()->touchEntriesWithImage(self::IMAGE, new \DateTimeImmutable('2026-11-10 08:00:00'));

        self::assertSame([
            self::POST => '2026-11-10 08:00:00',
            self::OTHER_POST => '2026-11-07 10:00:00',
            self::OLD_POST => '2026-11-10 08:00:00',
            self::NEWER_POST => '2026-12-01 10:00:00',
            self::TAG => '2026-11-07 10:00:00',
            self::OTHER_TAG => '2026-11-07 10:00:00',
        ], $this->entries());
        self::assertSame([
            SitemapFile::TAGS => '2026-11-07 10:00:00',
            'posts-2024' => '2026-12-01 10:00:00',
            'posts-2025' => '2026-11-10 08:00:00',
            'posts-2026' => '2026-11-10 08:00:00',
        ], $this->files());
    }

    public function testItMovesTheEntriesOfTheResourcesGivenAndTheirFiles(): void
    {
        $this->repository()->touchEntries([self::TAG, '01a1e500-0000-7000-8000-000000000009'], new \DateTimeImmutable('2026-11-10 08:00:00'));

        self::assertSame(['2026-11-10 08:00:00', '2026-11-07 10:00:00'], [$this->entries()[self::TAG], $this->entries()[self::OTHER_TAG]]);
        self::assertSame(['2026-11-10 08:00:00', '2026-11-07 10:00:00'], [$this->files()[SitemapFile::TAGS], $this->files()['posts-2026']]);
    }

    /**
     * Neither no resource, nor an image no post opens with, changes anything.
     */
    public function testNothingToTouchChangesNothing(): void
    {
        $entries = $this->entries();
        $files = $this->files();

        $this->repository()->touchEntries([], new \DateTimeImmutable('2026-11-10 08:00:00'));
        $this->repository()->touchEntriesWithImage('01a1e800-0000-7000-8000-000000000009', new \DateTimeImmutable('2026-11-10 08:00:00'));

        self::assertSame([$entries, $files], [$this->entries(), $this->files()]);
    }

    /**
     * @return array<mixed> by resource, as the table keeps them
     */
    private function entries(): array
    {
        return $this->connection()->fetchAllKeyValue('SELECT resource_id, lastmod FROM web_sitemap_entry ORDER BY path');
    }

    /**
     * @return array<mixed> by name
     */
    private function files(): array
    {
        return $this->connection()->fetchAllKeyValue('SELECT name, lastmod FROM web_sitemap_file ORDER BY name');
    }

    private function repository(): SitemapRepository
    {
        return self::getContainer()->get(SitemapRepository::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
