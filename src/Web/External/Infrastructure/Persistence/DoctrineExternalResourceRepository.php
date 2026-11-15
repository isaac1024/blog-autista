<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\DomainError;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceId;
use Web\External\Domain\ExternalResourceRepository;

/**
 * Outbound adapter that persists and reads the external resources on the
 * site through Doctrine ORM.
 */
#[AsAlias(ExternalResourceRepository::class)]
final readonly class DoctrineExternalResourceRepository implements ExternalResourceRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(ExternalResource $resource): void
    {
        $this->entityManager->persist($resource);
        $this->entityManager->flush();
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
    public function availableAmong(array $ids): array
    {
        $valid = [];

        foreach ($ids as $id) {
            try {
                $valid[] = new ExternalResourceId($id)->value();
            } catch (DomainError) {
                // Not the id of a resource: none is on the site.
            }
        }

        if ([] === $valid) {
            return [];
        }

        /** @var Query<null, ExternalResource> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('resource')
            ->from(ExternalResource::class, 'resource')
            ->where('resource.id IN (:ids) AND resource.available = true')
            ->setParameter('ids', $valid)
            ->getQuery();
        $found = [];

        foreach ($query->getResult() as $resource) {
            $found[$resource->id()->value()] = $resource;
        }

        return $found;
    }
}
