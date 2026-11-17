<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Persistence;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
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

    /**
     * Locked while changed, as the entries of a section or a tag every post
     * filed under it touches.
     */
    #[\Override]
    public function entries(array $resourceIds): array
    {
        if ([] === $resourceIds) {
            return [];
        }

        /** @var Query<null, SitemapEntry> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('entry')
            ->from(SitemapEntry::class, 'entry')
            ->where('entry.resourceId IN (:ids)')
            // Always in the same order, so two changes never lock them crosswise.
            ->orderBy('entry.resourceId')
            ->setParameter('ids', $resourceIds)
            ->getQuery();

        if ($this->isChanging()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getResult();
    }

    #[\Override]
    public function entriesWithImage(string $imageId): array
    {
        return $this->entityManager->getRepository(SitemapEntry::class)->findBy(['imageId' => $imageId]);
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
