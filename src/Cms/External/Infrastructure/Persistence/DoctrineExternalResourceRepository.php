<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Persistence;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads external resources through
 * Doctrine ORM.
 */
#[AsAlias(ExternalResourceRepository::class)]
final readonly class DoctrineExternalResourceRepository implements ExternalResourceRepository
{
    /**
     * Criteria field names mapped onto the ExternalResource properties.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'title' => 'title',
        'availability' => 'availability',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(ExternalResource $resource): void
    {
        $this->entityManager->persist($resource);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Besides the time-ordered id, the address is the only unique
            // column: another resource took it since it was checked.
            $holder = $this->findByUrl($resource->version()->url());

            throw InvalidResourceUrl::taken(null === $holder ? '' : $holder->id()->value());
        }
    }

    #[\Override]
    public function delete(ExternalResource $resource): void
    {
        $this->entityManager->remove($resource);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(ExternalResourceId $id): ?ExternalResource
    {
        return $this->entityManager->find(ExternalResource::class, $id);
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, ExternalResource> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('resource')
            ->from(ExternalResource::class, 'resource')
            ->where('resource.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ExternalResourceId $id): string => $id->value(), $ids))
            ->getQuery();

        return $query->getResult();
    }

    #[\Override]
    public function findByUrl(ResourceUrl $url): ?ExternalResource
    {
        return $this->entityManager->getRepository(ExternalResource::class)->findOneBy(['urlHash' => $url->hash()]);
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        return new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)->matching($this->entityManager, ExternalResource::class, 'resource', $criteria);
    }

    #[\Override]
    public function countUnavailable(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(resource)')
            ->from(ExternalResource::class, 'resource')
            ->where('resource.status = :published AND resource.availability = :unavailable')
            ->setParameter('published', ResourceStatus::Published)
            ->setParameter('unavailable', Availability::Unavailable)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
