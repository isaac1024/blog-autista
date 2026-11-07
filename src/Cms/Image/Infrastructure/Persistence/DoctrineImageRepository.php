<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Persistence;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists images through Doctrine ORM.
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
}
