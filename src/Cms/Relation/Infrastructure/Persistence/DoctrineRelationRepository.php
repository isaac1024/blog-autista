<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Persistence;

use Cms\Relation\Domain\Relation;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads relations through Doctrine ORM.
 */
#[AsAlias(RelationRepository::class)]
final readonly class DoctrineRelationRepository implements RelationRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function replace(ResourceType $sourceType, string $sourceId, ResourceType $targetType, array $targetIds): void
    {
        $this->entityManager->wrapInTransaction(function () use ($sourceType, $sourceId, $targetType, $targetIds): void {
            $this->entityManager->createQueryBuilder()
                ->delete(Relation::class, 'relation')
                ->where('relation.sourceType = :sourceType AND relation.sourceId = :sourceId AND relation.targetType = :targetType')
                ->setParameter('sourceType', $sourceType)
                ->setParameter('sourceId', $sourceId)
                ->setParameter('targetType', $targetType)
                ->getQuery()
                ->execute();

            // The transaction flushes them as it commits.
            foreach (array_unique($targetIds) as $targetId) {
                $this->entityManager->persist(Relation::between($sourceType, $sourceId, $targetType, $targetId));
            }
        });
    }

    #[\Override]
    public function isUsed(ResourceType $targetType, string $targetId): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(relation)')
            ->from(Relation::class, 'relation')
            ->where('relation.targetType = :targetType AND relation.targetId = :targetId')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->getQuery()
            ->getSingleScalarResult();

        return 0 < (int) $count;
    }

    #[\Override]
    public function usersOf(ResourceType $targetType, string $targetId, int $limit, int $offset): array
    {
        /** @var Query<null, Relation> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('relation')
            // Pages first, then images, then the settings, whatever their names.
            ->addSelect('CASE WHEN relation.sourceType = :page THEN 0 WHEN relation.sourceType = :image THEN 1 ELSE 2 END AS HIDDEN sourceOrder')
            ->from(Relation::class, 'relation')
            ->where('relation.targetType = :targetType AND relation.targetId = :targetId')
            ->orderBy('sourceOrder')
            // Ids are unique within a type, so every slice is the same.
            ->addOrderBy('relation.sourceId')
            ->setParameter('page', ResourceType::Page)
            ->setParameter('image', ResourceType::Image)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }
}
