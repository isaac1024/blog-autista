<?php

declare(strict_types=1);

namespace Web\Tests\External\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

final class DoctrineExternalResourceRepositoryTest extends KernelTestCase
{
    use MappedTables;

    public function testItSavesTheResourceSoItCanBeReadBack(): void
    {
        $resource = ExternalResource::publish(CmsExternalResourcePublishedMother::create(image: null, imageShape: null, available: false));

        $this->repository()->save($resource);
        $this->entityManager()->clear();

        self::assertEquals($resource, $this->repository()->find(ExternalResourceIdMother::create()));
    }

    /**
     * One unavailable, or not on the site, is none; neither is an id that is
     * no id at all.
     */
    public function testItFindsOnlyTheAvailableResourcesAmongThoseGivenByTheirIds(): void
    {
        $available = ExternalResourceIdMother::create();
        $unavailable = ExternalResourceIdMother::random();
        $this->repository()->save(ExternalResource::publish(CmsExternalResourcePublishedMother::create()));
        $this->repository()->save(ExternalResource::publish(CmsExternalResourcePublishedMother::create(id: $unavailable->value(), available: false)));
        $this->repository()->save(ExternalResource::publish(CmsExternalResourcePublishedMother::create(id: ExternalResourceIdMother::random()->value())));
        $this->entityManager()->clear();

        $found = $this->repository()->availableAmong([$available->value(), $unavailable->value(), ExternalResourceIdMother::random()->value(), 'no-es-un-id']);

        self::assertSame([$available->value()], array_keys($found));
        self::assertSame([], $this->repository()->availableAmong(['no-es-un-id']));
    }

    public function testItDeletesOnlyTheGivenResource(): void
    {
        $kept = ExternalResource::publish(CmsExternalResourcePublishedMother::create(id: ExternalResourceIdMother::random()->value()));
        $deleted = ExternalResource::publish(CmsExternalResourcePublishedMother::create());
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->find(ExternalResourceIdMother::create()));
        self::assertNotNull($this->repository()->find($kept->id()));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_external_resource');
    }

    private function repository(): ExternalResourceRepository
    {
        return self::getContainer()->get(ExternalResourceRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
