<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Persistence;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\Source;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads redirects through Doctrine ORM.
 *
 * Every redirect it reads comes from the database as it is now, even one
 * Doctrine holds already: a change made while holding its address works on
 * what another change saved meanwhile, never on what was read before. A
 * redirect saved from what was read before another change is refused.
 */
#[AsAlias(RedirectRepository::class)]
final readonly class DoctrineRedirectRepository implements RedirectRepository
{
    /**
     * Criteria field names mapped onto the Redirect properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'source' => 'source.value',
        'target_type' => 'target.type',
        'target_id' => 'target.id',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Redirect $redirect): void
    {
        $this->entityManager->persist($redirect);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Besides the id, the address is the only unique column.
            throw InvalidRedirect::sourceTaken($redirect->source());
        } catch (OptimisticLockException) {
            throw InvalidRedirect::changedMeanwhile($redirect->source());
        }
    }

    #[\Override]
    public function delete(Redirect $redirect): void
    {
        $this->entityManager->remove($redirect);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(RedirectId $id): ?Redirect
    {
        return $this->fresh(['id' => $id->value()])[0] ?? null;
    }

    #[\Override]
    public function sourceOf(RedirectId $id): ?Source
    {
        $source = $this->entityManager->createQueryBuilder()
            ->select('redirect.source.value')
            ->from(Redirect::class, 'redirect')
            ->where('redirect.id = :id')
            ->setParameter('id', $id->value())
            ->getQuery()
            ->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);

        return \is_string($source) ? Source::fromStored($source) : null;
    }

    #[\Override]
    public function findBySource(Source $source): ?Redirect
    {
        return $this->fresh(['source.value' => $source->value()])[0] ?? null;
    }

    #[\Override]
    public function isTargeted(Resource $resource): bool
    {
        return [] !== $this->fresh(['target.type' => $resource->type()->value, 'target.id' => $resource->id()]);
    }

    #[\Override]
    public function findTargeting(Resource $resource): array
    {
        return $this->fresh(['target.type' => $resource->type()->value, 'target.id' => $resource->id()]);
    }

    #[\Override]
    public function sourcesTargeting(Resource $resource): array
    {
        /** @var list<string> $sources */
        $sources = $this->entityManager->createQueryBuilder()
            ->select('redirect.source.value')
            ->from(Redirect::class, 'redirect')
            ->where('redirect.target.type = :type AND redirect.target.id = :id')
            ->setParameter('type', $resource->type()->value)
            ->setParameter('id', $resource->id())
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(Source::fromStored(...), $sources);
    }

    #[\Override]
    public function findByOrigin(Resource $resource): array
    {
        return $this->fresh(['origin.type' => $resource->type()->value, 'origin.id' => $resource->id()]);
    }

    #[\Override]
    public function findByOrigins(ResourceType $type, array $ids): array
    {
        return [] === $ids ? [] : $this->fresh(['origin.type' => $type->value, 'origin.id' => $ids]);
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        return new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)->matching($this->entityManager, Redirect::class, 'redirect', $criteria);
    }

    /**
     * The redirects whose fields have the values, read from the database even
     * when Doctrine holds them already: those it holds are let go first, and
     * read again. Every change to a redirect is saved at once, so letting one
     * go loses nothing. Refreshing them in place isn't an option: Doctrine
     * would write again their readonly fields.
     *
     * @param array<string, list<string>|string> $values by the path of each field, embeddables included
     *
     * @return list<Redirect>
     */
    private function fresh(array $values): array
    {
        $ids = $this->matchingIds($values);

        foreach ($ids as $id) {
            $held = $this->entityManager->getUnitOfWork()->tryGetById($id, Redirect::class);

            if ($held instanceof Redirect) {
                $this->entityManager->detach($held);
            }
        }

        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, Redirect> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('redirect')
            ->from(Redirect::class, 'redirect')
            ->where('redirect.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * The ids of the redirects whose fields have the values.
     *
     * @param array<string, list<string>|string> $values
     *
     * @return list<string>
     */
    private function matchingIds(array $values): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('redirect.id')
            ->from(Redirect::class, 'redirect');
        $index = 0;

        foreach ($values as $field => $value) {
            $parameter = 'value_'.$index++;
            $queryBuilder
                ->andWhere(\is_array($value) ? \sprintf('redirect.%s IN (:%s)', $field, $parameter) : \sprintf('redirect.%s = :%s', $field, $parameter))
                ->setParameter($parameter, $value);
        }

        return array_values(array_map(
            static fn (mixed $id): string => $id instanceof RedirectId ? $id->value() : (string) (\is_scalar($id) ? $id : ''),
            $queryBuilder->getQuery()->getSingleColumnResult(),
        ));
    }
}
