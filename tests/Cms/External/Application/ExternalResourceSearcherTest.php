<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Application\ExternalResourceSearcher;
use Cms\External\Application\ExternalResourcesResponse;
use Cms\External\Domain\Availability;
use Cms\External\Domain\AvailabilityFilter;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\TitleFilter;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class ExternalResourceSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfResources(ExternalResourceSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{ExternalResourceSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = ExternalResourceSearcher::PAGE_SIZE;
        // One more than a page tells whether another follows.
        $limit = $pageSize + 1;

        yield 'no title, first page' => [
            new ExternalResourceSearcher('', false, 1),
            new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'blank title does not filter' => [
            new ExternalResourceSearcher('   ', false, 1),
            new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'title is trimmed before filtering' => [
            new ExternalResourceSearcher('  vídeo  ', false, 1),
            new Criteria(limit: $limit, filters: [TitleFilter::contains('vídeo')], order: $newestFirst, offset: 0),
        ];
        yield 'only those whose address is gone' => [
            new ExternalResourceSearcher('', true, 1),
            new Criteria(limit: $limit, filters: [AvailabilityFilter::equalTo(Availability::Unavailable)], order: $newestFirst, offset: 0),
        ];
        yield 'title, gone and page together' => [
            new ExternalResourceSearcher('vídeo', true, 3),
            new Criteria(limit: $limit, filters: [TitleFilter::contains('vídeo'), AvailabilityFilter::equalTo(Availability::Unavailable)], order: $newestFirst, offset: 2 * $pageSize),
        ];
    }

    #[DataProvider('pageSizes')]
    public function testItTellsWhetherMoreResourcesFollow(int $found, int $listed, bool $hasMore): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('matching')->willReturn(array_map(
            static fn (int $index): ExternalResource => ExternalResourceMother::article(id: \sprintf('01a1e2a0-0000-7000-8000-0000000001%02d', $index)),
            range(1, $found),
        ));

        $resources = new ExternalResourceSearcher('', false, 1)($repository);

        self::assertCount($listed, $resources->resources);
        self::assertSame($hasMore, $resources->hasMore);
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function pageSizes(): iterable
    {
        yield 'fewer than a page' => [3, 3, false];
        yield 'exactly a page' => [ExternalResourceSearcher::PAGE_SIZE, ExternalResourceSearcher::PAGE_SIZE, false];
        yield 'more than a page' => [ExternalResourceSearcher::PAGE_SIZE + 1, ExternalResourceSearcher::PAGE_SIZE, true];
    }

    public function testItReturnsTheFoundResourcesInTheRepositoryOrder(): void
    {
        $video = ExternalResourceMother::video();
        $article = ExternalResourceMother::article(id: '01a1e2a0-0000-7000-8000-000000000002');
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('matching')->willReturn([$video, $article]);

        self::assertEquals(
            new ExternalResourcesResponse([ExternalResourceResponse::fromResource($video), ExternalResourceResponse::fromResource($article)], false),
            new ExternalResourceSearcher('', false, 1)($repository),
        );
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new ExternalResourceSearcher('', false, 0)($repository);
    }
}
