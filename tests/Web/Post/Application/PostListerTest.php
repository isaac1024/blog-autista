<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Web\Post\Application\PostCardResponse;
use Web\Post\Application\PostLister;
use Web\Post\Application\PostsPageNotFound;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostCard;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostCardMother;
use Web\Tests\Post\Domain\PostIdMother;

/**
 * A page of a list of posts: twenty of the selection, from where the page
 * starts, and whether another follows. A page that can't be, or past the
 * last, isn't there; the first always is, empty or not.
 */
final class PostListerTest extends TestCase
{
    private const string SECTION = CmsPostPublishedMother::SECTION;

    public function testAPageReadsOneMoreThanItShowsToTellWhetherAnotherFollows(): void
    {
        $cards = self::cards(21);
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('listed')->with(new PostSelection(self::SECTION, null, []), 20, 21)->willReturn($cards);

        $page = new PostLister(self::SECTION, null, [], 2)($repository);

        self::assertEquals([array_map(PostCardResponse::fromCard(...), \array_slice($cards, 0, 20)), true], [$page->posts, $page->hasMore]);
    }

    public function testTheLastPageTellsNoneFollows(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('listed')->willReturn(self::cards(20));

        self::assertSame([20, false], [\count(new PostLister(null, null, [], 1)($repository)->posts), new PostLister(null, null, [], 1)($repository)->hasMore]);
    }

    public function testTheFirstPageIsThereEvenEmpty(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('listed')->willReturn([]);

        $page = new PostLister(null, null, [], 1)($repository);

        self::assertSame([[], false], [$page->posts, $page->hasMore]);
    }

    /**
     * A page that can't be is looked up in nothing; one past the last is
     * found empty, and isn't there.
     */
    #[DataProvider('pagesThatAreNot')]
    public function testAPageThatCannotBeOrIsPastTheLastIsNotThere(int $page, bool $looked): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects($looked ? self::once() : self::never())->method('listed')->willReturn([]);

        $this->expectExceptionObject(PostsPageNotFound::numbered($page));

        new PostLister(null, null, [], $page)($repository);
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function pagesThatAreNot(): iterable
    {
        yield 'page 0' => [0, false];
        yield 'a page below it' => [-1, false];
        yield 'one past the last' => [3, true];
    }

    /**
     * @return list<PostCard>
     */
    private static function cards(int $count): array
    {
        return array_map(
            static fn (int $index): PostCard => PostCardMother::of(Post::publish(CmsPostPublishedMother::create(id: PostIdMother::random()->value(), slug: 'entrada-'.$index))),
            range(1, $count),
        );
    }
}
