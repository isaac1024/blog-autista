<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Persistence;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads images through Doctrine ORM.
 */
#[AsAlias(ImageRepository::class)]
final readonly class DoctrineImageRepository implements ImageRepository
{
    /**
     * Criteria field names mapped onto the Image properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'alt' => 'version.alt.value',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Image $image): void
    {
        $this->entityManager->persist($image);
        $this->entityManager->flush();
    }

    #[\Override]
    public function delete(Image $image): void
    {
        $this->entityManager->remove($image);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(ImageId $id): ?Image
    {
        return $this->entityManager->find(Image::class, $id);
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, Image> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('image')
            ->from(Image::class, 'image')
            ->where('image.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ImageId $id): string => $id->value(), $ids))
            ->getQuery();

        return $query->getResult();
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('image')
            ->from(Image::class, 'image');

        /** @var Query<null, Image> $query */
        $query = new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)
            ->apply($criteria, $queryBuilder, 'image')
            ->getQuery();

        return $query->getResult();
    }
}
