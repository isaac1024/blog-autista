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
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads redirects through Doctrine ORM.
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
        } catch (UniqueConstraintViolationException $exception) {
            // Besides the id, the address is the only unique column.
            throw InvalidRedirect::sourceTaken($redirect->source());
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
        return $this->entityManager->find(Redirect::class, $id);
    }

    #[\Override]
    public function findBySource(Source $source): ?Redirect
    {
        return $this->entityManager->getRepository(Redirect::class)->findOneBy(['source.value' => $source->value()]);
    }

    #[\Override]
    public function isTargeted(Resource $resource): bool
    {
        return null !== $this->entityManager->getRepository(Redirect::class)->findOneBy([
            'target.type' => $resource->type(),
            'target.id' => $resource->id(),
        ]);
    }

    #[\Override]
    public function findTargeting(Resource $resource): array
    {
        return $this->entityManager->getRepository(Redirect::class)->findBy([
            'target.type' => $resource->type(),
            'target.id' => $resource->id(),
        ]);
    }

    #[\Override]
    public function findByOrigin(Resource $resource): array
    {
        return $this->entityManager->getRepository(Redirect::class)->findBy([
            'origin.type' => $resource->type(),
            'origin.id' => $resource->id(),
        ]);
    }

    #[\Override]
    public function findByOrigins(ResourceType $type, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->entityManager->getRepository(Redirect::class)->findBy(['origin.type' => $type, 'origin.id' => $ids]);
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('redirect')
            ->from(Redirect::class, 'redirect');

        /** @var Query<null, Redirect> $query */
        $query = new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)
            ->apply($criteria, $queryBuilder, 'redirect')
            ->getQuery();

        return $query->getResult();
    }
}
