<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Persistence;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother as Ids;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRelationRepositoryTest extends KernelTestCase
{
    private const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb96';

    public function testItKnowsNothingIsUsedUntilARelationIsKept(): void
    {
        self::assertFalse($this->repository()->isUsed(ResourceType::Image, Ids::IMAGE));
    }

    public function testItKeepsTheRelationsOfASource(): void
    {
        $repository = $this->repository();

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE, Ids::OTHER_IMAGE]);

        self::assertTrue($repository->isUsed(ResourceType::Image, Ids::IMAGE));
        self::assertTrue($repository->isUsed(ResourceType::Image, Ids::OTHER_IMAGE));
    }

    public function testItReplacesTheRelationsOfTheSourceWithTheNewOnes(): void
    {
        $repository = $this->repository();
        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE]);

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::OTHER_IMAGE]);

        self::assertFalse($repository->isUsed(ResourceType::Image, Ids::IMAGE));
        self::assertTrue($repository->isUsed(ResourceType::Image, Ids::OTHER_IMAGE));
    }

    public function testItRemovesEveryRelationOfASourceThatUsesNothingAnyLonger(): void
    {
        $repository = $this->repository();
        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE]);

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, []);

        self::assertFalse($repository->isUsed(ResourceType::Image, Ids::IMAGE));
    }

    /**
     * An image another page still embeds stays in use.
     */
    public function testItLeavesTheRelationsOfOtherSources(): void
    {
        $repository = $this->repository();
        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE]);
        $repository->replace(ResourceType::Page, self::OTHER_PAGE, ResourceType::Image, [Ids::IMAGE]);

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, []);

        self::assertTrue($repository->isUsed(ResourceType::Image, Ids::IMAGE));
    }

    public function testItKeepsAResourceUsedTwiceOnce(): void
    {
        $repository = $this->repository();

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE, Ids::IMAGE]);
        $repository->replace(ResourceType::Page, self::OTHER_PAGE, ResourceType::Image, []);

        self::assertTrue($repository->isUsed(ResourceType::Image, Ids::IMAGE));
    }

    /**
     * Ids of resources of different types are told apart.
     */
    public function testItTellsTypesOfResourceApart(): void
    {
        $repository = $this->repository();

        $repository->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE]);

        self::assertFalse($repository->isUsed(ResourceType::Page, Ids::IMAGE));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
