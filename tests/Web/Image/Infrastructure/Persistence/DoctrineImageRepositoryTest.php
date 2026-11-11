<?php

declare(strict_types=1);

namespace Web\Tests\Image\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Image\Domain\ImageIdMother;

final class DoctrineImageRepositoryTest extends KernelTestCase
{
    use MappedTables;

    /**
     * Every field comes back as saved: its texts at their longest, where the
     * CMS keeps its file and the last event it took.
     */
    public function testItSavesTheImageSoItCanBeReadBack(): void
    {
        $id = ImageIdMother::random();
        $image = Image::publish(CmsImagePublishedMother::create(
            id: $id->value(),
            alt: str_repeat('á', 125),
            caption: Text::stored(Text::paragraph(str_repeat('é', 200))),
            path: '2027/01/'.$id->value().'.webp',
        ));

        $this->repository()->save($image);
        $this->entityManager()->clear();

        self::assertEquals($image, $this->repository()->find($id));
    }

    public function testItFindsNoImageWhenTheIdDoesNotExist(): void
    {
        $this->repository()->save(Image::publish(CmsImagePublishedMother::create(id: ImageIdMother::random()->value())));

        self::assertNull($this->repository()->find(ImageIdMother::random()));
    }

    public function testItFindsOnlyTheImagesOnTheSiteAmongThoseGiven(): void
    {
        $first = ImageIdMother::random();
        $second = ImageIdMother::random();
        $images = [Image::publish(CmsImagePublishedMother::create(id: $first->value())), Image::publish(CmsImagePublishedMother::create(id: $second->value()))];
        $repository = $this->repository();
        $repository->save($images[0]);
        $repository->save($images[1]);
        $repository->save(Image::publish(CmsImagePublishedMother::create(id: ImageIdMother::random()->value())));
        $this->entityManager()->clear();

        self::assertEqualsCanonicalizing($images, $repository->findByIds([$first, ImageIdMother::random(), $second]));
        self::assertSame([], $repository->findByIds([]));
    }

    public function testItDeletesOnlyTheGivenImage(): void
    {
        $keptId = ImageIdMother::random();
        $kept = Image::publish(CmsImagePublishedMother::create(id: $keptId->value()));
        $deletedId = ImageIdMother::random();
        $deleted = Image::publish(CmsImagePublishedMother::create(id: $deletedId->value()));
        $repository = $this->repository();
        $repository->save($kept);
        $repository->save($deleted);

        $repository->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($repository->find($deletedId));
        self::assertEquals($kept, $repository->find($keptId));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_image');
    }

    private function repository(): ImageRepository
    {
        return self::getContainer()->get(ImageRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
