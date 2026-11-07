<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Persistence;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineImageRepositoryTest extends KernelTestCase
{
    /**
     * Every field comes back as saved: its id, its text at its longest, its
     * path and its dates.
     */
    public function testItSavesTheImageSoItCanBeReadBack(): void
    {
        $id = ImageIdMother::random();
        $image = ImageMother::create(id: $id->value(), alt: str_repeat('á', 125), createdAt: '2027-01-31 23:59:59');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        self::getContainer()->get(ImageRepository::class)->save($image);
        $entityManager->clear();

        self::assertEquals($image, $entityManager->find(Image::class, $id));
    }
}
