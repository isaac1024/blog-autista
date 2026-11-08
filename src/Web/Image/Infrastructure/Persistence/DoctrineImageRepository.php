<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;

/**
 * Outbound adapter that persists and reads the images on the site through
 * Doctrine ORM.
 */
#[AsAlias(ImageRepository::class)]
final readonly class DoctrineImageRepository implements ImageRepository
{
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

        /** @var list<Image> $images */
        $images = $this->entityManager->createQueryBuilder()
            ->select('image')
            ->from(Image::class, 'image')
            ->where('image.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ImageId $id): string => $id->value(), $ids))
            ->getQuery()
            ->getResult();

        return $images;
    }
}
