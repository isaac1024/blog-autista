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

    /**
     * Removes, by their ids, only the relations no longer there, and adds
     * only those new: saving what didn't change writes nothing. A range is
     * never deleted, so saving a new post locks no gap of the index other
     * posts saved at once would insert into, which deadlocked them.
     */
    #[\Override]
    public function replace(ResourceType $sourceType, string $sourceId, ResourceType $targetType, array $targetIds): void
    {
        /** @var list<Relation> $kept */
        $kept = $this->entityManager->getRepository(Relation::class)->findBy(['sourceType' => $sourceType, 'sourceId' => $sourceId, 'targetType' => $targetType]);
        $wanted = array_values(array_unique($targetIds));
        $keptIds = [];

        foreach ($kept as $relation) {
            if (\in_array($relation->targetId(), $wanted, true)) {
                $keptIds[] = $relation->targetId();
            } else {
                $this->entityManager->remove($relation);
            }
        }

        foreach (array_diff($wanted, $keptIds) as $targetId) {
            $this->entityManager->persist(Relation::between($sourceType, $sourceId, $targetType, $targetId));
        }

        $this->entityManager->flush();
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
            // Pages first, then images, then the settings, then the authors, then the posts, whatever their names.
            ->addSelect('CASE WHEN relation.sourceType = :page THEN 0 WHEN relation.sourceType = :image THEN 1 WHEN relation.sourceType = :settings THEN 2 WHEN relation.sourceType = :author THEN 3 ELSE 4 END AS HIDDEN sourceOrder')
            ->from(Relation::class, 'relation')
            ->where('relation.targetType = :targetType AND relation.targetId = :targetId')
            ->orderBy('sourceOrder')
            // Ids are unique within a type, so every slice is the same.
            ->addOrderBy('relation.sourceId')
            ->setParameter('page', ResourceType::Page)
            ->setParameter('image', ResourceType::Image)
            ->setParameter('settings', ResourceType::Settings)
            ->setParameter('author', ResourceType::Author)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }
}
