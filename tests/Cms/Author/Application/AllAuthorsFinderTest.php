<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AllAuthorsFinder;
use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class AllAuthorsFinderTest extends TestCase
{
    /**
     * By name, in as many pages as the repository needs: a full page tells
     * another may follow, one short of it that none does.
     */
    public function testItFindsEveryAuthorByNameAcrossPages(): void
    {
        $first = array_map(static fn (int $i): Author => AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Autor '.$i, slug: 'autor-'.$i), range(1, Criteria::MAX_LIMIT));
        $last = [AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Zoe', slug: 'zoe')];
        $byName = new Order('name', OrderDirection::Ascending);
        $asked = [];
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::exactly(2))
            ->method('matching')
            ->willReturnCallback(static function (Criteria $criteria) use (&$asked, $first, $last): array {
                $asked[] = $criteria;

                return 1 === \count($asked) ? $first : $last;
            });

        self::assertEquals(array_map(AuthorResponse::fromAuthor(...), [...$first, ...$last]), new AllAuthorsFinder()($repository));
        self::assertEquals([
            new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $byName, offset: 0),
            new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $byName, offset: Criteria::MAX_LIMIT),
        ], $asked);
    }

    public function testItFindsNoneWithoutAuthors(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())->method('matching')->willReturn([]);

        self::assertSame([], new AllAuthorsFinder()($repository));
    }
}
