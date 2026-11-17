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

    /**
     * Read a kind of user at a time, in the order of the kinds, each by id
     * through the index of what it uses: ordering every user at once by its
     * kind would sort them all for each slice, thousands for a tag many
     * posts are filed under. How many each kind has, through the same index,
     * tells where the slice starts.
     */
    #[\Override]
    public function usersOf(ResourceType $targetType, string $targetId, int $limit, int $offset): array
    {
        $users = [];

        foreach (ResourceType::cases() as $sourceType) {
            if ($limit <= 0) {
                break;
            }

            $ofKind = $this->entityManager->createQueryBuilder()
                ->from(Relation::class, 'relation')
                ->where('relation.targetType = :targetType AND relation.targetId = :targetId AND relation.sourceType = :sourceType')
                ->setParameter('targetType', $targetType)
                ->setParameter('targetId', $targetId)
                ->setParameter('sourceType', $sourceType);
            $count = (int) (clone $ofKind)->select('COUNT(relation)')->getQuery()->getSingleScalarResult();

            if ($offset >= $count) {
                $offset -= $count;

                continue;
            }

            /** @var Query<null, Relation> $query */
            $query = $ofKind->select('relation')
                // Ids are unique within a kind, so every slice is the same.
                ->orderBy('relation.sourceId')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery();
            $slice = $query->getResult();
            $users = [...$users, ...$slice];
            $limit -= \count($slice);
            $offset = 0;
        }

        return $users;
    }
}
