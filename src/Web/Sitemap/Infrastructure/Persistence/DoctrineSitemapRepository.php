<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Sitemap\Domain\SitemapEntry;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapLine;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Outbound adapter that keeps the sitemap through Doctrine ORM, and reads
 * the lines of a file in plain SQL, one at a time: whether a section or a
 * tag lists some post, and where the image of a post on the site is, are
 * read from the tables of the posts and the images, through their indexes.
 */
#[AsAlias(SitemapRepository::class)]
final readonly class DoctrineSitemapRepository implements SitemapRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function entry(string $resourceId): ?SitemapEntry
    {
        return $this->entityManager->find(SitemapEntry::class, $resourceId);
    }

    #[\Override]
    public function touchEntriesWithImage(string $imageId, \DateTimeImmutable $moment): void
    {
        $this->touch('e.image_id = :image', ['image' => $imageId], [], $moment);
    }

    #[\Override]
    public function touchEntries(array $resourceIds, \DateTimeImmutable $moment): void
    {
        $this->touch('e.resource_id IN (:ids)', ['ids' => $resourceIds], ['ids' => ArrayParameterType::STRING], $moment);
    }

    #[\Override]
    public function saveEntry(SitemapEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    #[\Override]
    public function deleteEntry(SitemapEntry $entry): void
    {
        $this->entityManager->remove($entry);
        $this->entityManager->flush();
    }

    /**
     * Locked while changed: every post of a year counts in the same file,
     * and consumers of the queue applying two of them at once would each
     * write the count they read, which the database refuses as changed since.
     */
    #[\Override]
    public function file(string $name): ?SitemapFile
    {
        return $this->entityManager->find(SitemapFile::class, $name, $this->isChanging() ? LockMode::PESSIMISTIC_WRITE : LockMode::NONE);
    }

    #[\Override]
    public function saveFile(SitemapFile $file): void
    {
        $this->entityManager->persist($file);
        $this->entityManager->flush();
    }

    #[\Override]
    public function deleteFile(SitemapFile $file): void
    {
        $this->entityManager->remove($file);
        $this->entityManager->flush();
    }

    #[\Override]
    public function files(): array
    {
        return $this->entityManager->getRepository(SitemapFile::class)->findBy([], ['name' => 'ASC']);
    }

    #[\Override]
    public function lines(string $file): iterable
    {
        $listing = match ($file) {
            SitemapFile::SECTIONS => ' AND EXISTS (SELECT 1 FROM web_post p WHERE p.section_id = e.resource_id)',
            SitemapFile::TAGS => ' AND EXISTS (SELECT 1 FROM web_post_tag t WHERE t.term_id = e.resource_id)',
            default => '',
        };
        $rows = $this->entityManager->getConnection()->iterateAssociative(
            'SELECT e.path, e.lastmod, e.image_id, i.path AS image_path FROM web_sitemap_entry e LEFT JOIN web_image i ON i.id = e.image_id WHERE e.file = :file'.$listing.' ORDER BY e.path',
            ['file' => $file],
        );

        foreach ($rows as $row) {
            yield new SitemapLine(
                self::text($row['path']),
                new \DateTimeImmutable(self::text($row['lastmod'])),
                null === $row['image_id'] ? null : self::text($row['image_id']),
                null === $row['image_path'] ? null : self::text($row['image_path']),
            );
        }
    }

    #[\Override]
    public function imagesOffSite(string $file): array
    {
        return array_map(self::text(...), $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT e.image_id FROM web_sitemap_entry e LEFT JOIN web_image i ON i.id = e.image_id WHERE e.file = :file AND e.image_id IS NOT NULL AND i.id IS NULL',
            ['file' => $file],
        ));
    }

    /**
     * Moves the entries that match, and their files, to the moment, never
     * back, in two statements however many they are. The files go first, as
     * placing an entry locks its file before the entry, so two changes never
     * lock them crosswise. The statements go past the entities read: none
     * read in the same change is one they move, as a post touches the entries
     * of its section and tags, never its own, and their files, never that of
     * its year.
     *
     * @param array<string, mixed>              $parameters
     * @param array<string, ArrayParameterType> $types
     */
    private function touch(string $matching, array $parameters, array $types, \DateTimeImmutable $moment): void
    {
        $connection = $this->entityManager->getConnection();
        $touching = ['moment' => $moment] + $parameters;
        $touchingTypes = ['moment' => Types::DATETIME_IMMUTABLE] + $types;
        // With no entry or no file, the statements change nothing: an empty list is sent as NULL.
        /** @var list<string> $files */
        $files = $connection->fetchFirstColumn('SELECT DISTINCT e.file FROM web_sitemap_entry e WHERE '.$matching, $parameters, $types);

        $connection->executeStatement(
            'UPDATE web_sitemap_file SET lastmod = GREATEST(lastmod, :moment) WHERE name IN (:files) ORDER BY name',
            ['moment' => $moment, 'files' => $files],
            ['moment' => Types::DATETIME_IMMUTABLE, 'files' => ArrayParameterType::STRING],
        );
        $connection->executeStatement('UPDATE web_sitemap_entry e SET e.lastmod = GREATEST(e.lastmod, :moment) WHERE '.$matching, $touching, $touchingTypes);
    }

    /**
     * Whether it is read to be changed, within the transaction of a
     * projection, rather than to be served.
     */
    private function isChanging(): bool
    {
        return $this->entityManager->getConnection()->isTransactionActive();
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('A line of the sitemap has no text where it should.');
    }
}
