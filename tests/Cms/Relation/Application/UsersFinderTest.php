<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\UserResponse;
use Cms\Relation\Application\UsersFinder;
use Cms\Relation\Application\UsersResponse;
use Cms\Relation\Domain\Relation;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother as Ids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\InvalidCriteria;

final class UsersFinderTest extends TestCase
{
    /**
     * One more than a page, to know whether another follows.
     */
    #[DataProvider('slices')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfUsers(int $page, int $offset): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())
            ->method('usersOf')
            ->with(ResourceType::Image, Ids::IMAGE, UsersFinder::PAGE_SIZE + 1, $offset)
            ->willReturn([]);

        (new UsersFinder(ResourceType::Image, Ids::IMAGE, $page))($repository);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function slices(): iterable
    {
        yield 'first page' => [1, 0];
        yield 'second page skips the first one' => [2, UsersFinder::PAGE_SIZE];
        yield 'later pages skip every previous one' => [5, 4 * UsersFinder::PAGE_SIZE];
    }

    public function testItGivesTheUsersInTheRepositoryOrder(): void
    {
        $repository = self::createStub(RelationRepository::class);
        $repository->method('usersOf')->willReturn([
            Relation::between(ResourceType::Page, Ids::PAGE, ResourceType::Image, Ids::IMAGE),
            Relation::between(ResourceType::Settings, Ids::OTHER_IMAGE, ResourceType::Image, Ids::IMAGE),
        ]);

        self::assertEquals(
            new UsersResponse([new UserResponse('page', Ids::PAGE), new UserResponse('settings', Ids::OTHER_IMAGE)], hasMore: false),
            (new UsersFinder(ResourceType::Image, Ids::IMAGE, 1))($repository),
        );
    }

    #[DataProvider('pageSizes')]
    public function testItTellsWhetherMoreUsersFollow(int $found, int $listed, bool $hasMore): void
    {
        $repository = self::createStub(RelationRepository::class);
        $repository->method('usersOf')->willReturn(array_map(
            static fn (int $index): Relation => Relation::between(ResourceType::Page, \sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fb%02d', $index), ResourceType::Image, Ids::IMAGE),
            range(1, $found),
        ));

        $users = (new UsersFinder(ResourceType::Image, Ids::IMAGE, 1))($repository);

        self::assertCount($listed, $users->users);
        self::assertSame($hasMore, $users->hasMore);
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function pageSizes(): iterable
    {
        yield 'fewer than a page' => [3, 3, false];
        yield 'exactly a page' => [UsersFinder::PAGE_SIZE, UsersFinder::PAGE_SIZE, false];
        yield 'more than a page' => [UsersFinder::PAGE_SIZE + 1, UsersFinder::PAGE_SIZE, true];
    }

    #[DataProvider('invalidPages')]
    public function testItRejectsPageNumbersBelowOne(int $page): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::never())->method('usersOf');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage($page));

        (new UsersFinder(ResourceType::Image, Ids::IMAGE, $page))($repository);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
