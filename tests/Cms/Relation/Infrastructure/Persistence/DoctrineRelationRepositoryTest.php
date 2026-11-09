<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Persistence;

use Cms\Relation\Domain\Relation;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother as Ids;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRelationRepositoryTest extends KernelTestCase
{
    private const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb96';

    private const string SETTINGS = '01a1c0de-5e77-7a5e-8a11-000000000001';

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

    /**
     * Pages first, then images, then the settings, each type by id, whatever
     * order they were related in; only the users of the resource given.
     */
    public function testItListsTheUsersOfAResourceInOrder(): void
    {
        $repository = $this->repository();
        $repository->replace(ResourceType::Settings, self::SETTINGS, ResourceType::Page, [Ids::PAGE]);
        $repository->replace(ResourceType::Image, Ids::OTHER_IMAGE, ResourceType::Page, [Ids::PAGE]);
        $repository->replace(ResourceType::Page, self::OTHER_PAGE, ResourceType::Page, [Ids::PAGE]);
        $repository->replace(ResourceType::Image, Ids::IMAGE, ResourceType::Page, [Ids::PAGE]);
        // Uses another page, and an image with the same id as the page.
        $repository->replace(ResourceType::Page, Ids::IMAGE, ResourceType::Page, [self::OTHER_PAGE]);
        $repository->replace(ResourceType::Page, Ids::IMAGE, ResourceType::Image, [Ids::PAGE]);

        self::assertSame([
            ['page', self::OTHER_PAGE],
            ['image', Ids::IMAGE],
            ['image', Ids::OTHER_IMAGE],
            ['settings', self::SETTINGS],
        ], $this->sources($repository->usersOf(ResourceType::Page, Ids::PAGE, 10, 0)));
    }

    public function testItListsASliceOfTheUsers(): void
    {
        $repository = $this->repository();
        $repository->replace(ResourceType::Settings, self::SETTINGS, ResourceType::Page, [Ids::PAGE]);
        $repository->replace(ResourceType::Image, Ids::IMAGE, ResourceType::Page, [Ids::PAGE]);
        $repository->replace(ResourceType::Page, self::OTHER_PAGE, ResourceType::Page, [Ids::PAGE]);

        self::assertSame(
            [['image', Ids::IMAGE]],
            $this->sources($repository->usersOf(ResourceType::Page, Ids::PAGE, 1, 1)),
        );
    }

    public function testItListsNoUsersOfAResourceNothingUses(): void
    {
        $this->repository()->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, [Ids::IMAGE]);

        self::assertSame([], $this->repository()->usersOf(ResourceType::Image, Ids::OTHER_IMAGE, 10, 0));
    }

    /**
     * @param list<Relation> $relations
     *
     * @return list<array{string, string}>
     */
    private function sources(array $relations): array
    {
        return array_map(static fn (Relation $relation): array => [$relation->sourceType()->value, $relation->sourceId()], $relations);
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
