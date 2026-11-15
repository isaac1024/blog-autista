<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostSearcher;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\TitleFilter;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

final class PostSearcherTest extends TestCase
{
    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfPosts(PostSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('matching')->with(self::equalTo($expected))->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{PostSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);
        $pageSize = PostSearcher::PAGE_SIZE;
        // One more than a page tells whether another follows.
        $limit = $pageSize + 1;

        yield 'every post, the first page' => [new PostSearcher('', 1, null), new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0)];
        yield 'a blank title filters nothing' => [new PostSearcher('   ', 1, null), new Criteria(limit: $limit, filters: [], order: $newestFirst, offset: 0)];
        yield 'a title, trimmed, filters by its words' => [new PostSearcher('  ruido metro ', 1, null), new Criteria(limit: $limit, filters: [TitleFilter::matches('ruido metro')], order: $newestFirst, offset: 0)];
        yield 'a later page skips every previous one' => [new PostSearcher('ruido', 3, null), new Criteria(limit: $limit, filters: [TitleFilter::matches('ruido')], order: $newestFirst, offset: 2 * $pageSize)];
    }

    public function testItTellsWhetherMorePostsFollow(): void
    {
        $posts = array_map(static fn (): \Cms\Post\Domain\Post => PostMother::create(id: PostIdMother::random()->value()), range(0, PostSearcher::PAGE_SIZE));
        $repository = self::createStub(PostRepository::class);
        $repository->method('matching')->willReturn($posts);

        $found = new PostSearcher('', 1, null)($repository);

        self::assertTrue($found->hasMore);
        self::assertSame(
            array_map(static fn ($post): string => $post->id()->value(), \array_slice($posts, 0, PostSearcher::PAGE_SIZE)),
            array_map(static fn (PostResponse $post): string => $post->id, $found->posts),
        );
    }

    public function testItTellsNoMorePostsFollowAFullLastPage(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('matching')->willReturn(array_map(static fn (): \Cms\Post\Domain\Post => PostMother::create(id: PostIdMother::random()->value()), range(1, PostSearcher::PAGE_SIZE)));

        $found = new PostSearcher('', 1, null)($repository);

        self::assertFalse($found->hasMore);
        self::assertCount(PostSearcher::PAGE_SIZE, $found->posts);
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new PostSearcher('', 0, null)(self::createStub(PostRepository::class));
    }
}
