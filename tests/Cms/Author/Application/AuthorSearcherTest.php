<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorSearcher;
use Cms\Author\Application\AuthorsResponse;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\NameFilter;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class AuthorSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfAuthors(AuthorSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{AuthorSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = AuthorSearcher::PAGE_SIZE;
        // One more than a page tells whether another follows.
        $limit = $pageSize + 1;

        yield 'no name, first page' => [
            new AuthorSearcher('', 1),
            new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'blank name does not filter' => [
            new AuthorSearcher('   ', 1),
            new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'name is trimmed before filtering' => [
            new AuthorSearcher(' abreu ', 1),
            new Criteria(limit: $limit, filters: [NameFilter::contains('abreu')], order: $newestFirst, offset: 0),
        ];
        yield 'later pages skip every previous one' => [
            new AuthorSearcher('abreu', 3),
            new Criteria(limit: $limit, filters: [NameFilter::contains('abreu')], order: $newestFirst, offset: 2 * $pageSize),
        ];
    }

    public function testItReturnsTheFoundAuthorsInTheRepositoryOrder(): void
    {
        $newer = AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Ana Ruiz', slug: 'ana');
        $older = AuthorMother::create(id: AuthorIdMother::random()->value());
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('matching')->willReturn([$newer, $older]);

        self::assertEquals(
            new AuthorsResponse([AuthorResponse::fromAuthor($newer), AuthorResponse::fromAuthor($older)], false),
            new AuthorSearcher('', 1)($repository),
        );
    }

    #[DataProvider('pageSizes')]
    public function testItTellsWhetherMoreAuthorsFollow(int $found, int $listed, bool $hasMore): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('matching')->willReturn(array_map(
            static fn (int $index): Author => AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Autor '.$index, slug: 'autor-'.$index),
            range(1, $found),
        ));

        $authors = new AuthorSearcher('', 1)($repository);

        self::assertCount($listed, $authors->authors);
        self::assertSame($hasMore, $authors->hasMore);
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function pageSizes(): iterable
    {
        yield 'fewer than a page' => [3, 3, false];
        yield 'exactly a page' => [AuthorSearcher::PAGE_SIZE, AuthorSearcher::PAGE_SIZE, false];
        yield 'more than a page' => [AuthorSearcher::PAGE_SIZE + 1, AuthorSearcher::PAGE_SIZE, true];
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new AuthorSearcher('', 0)($repository);
    }
}
