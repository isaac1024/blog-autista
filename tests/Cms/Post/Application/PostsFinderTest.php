<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;

final class PostsFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1c02';

    private const string MISSING = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1c03';

    /**
     * All of them are read at once, each id once; what is not an id, never;
     * they come in the order asked, whatever order the repository answers
     * in, and what nothing has finds none.
     */
    public function testItFindsThePostsWithTheGivenIdsAsTheCmsShowsThem(): void
    {
        $first = PostMother::create();
        $second = PostMother::published(id: self::OTHER);
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('findByIds')
            ->with(self::equalTo([new PostId(PostIdMother::DEFAULT_VALUE), new PostId(self::OTHER), new PostId(self::MISSING)]))
            ->willReturn([$second, $first]);

        self::assertEquals(
            [PostResponse::fromPost($first), PostResponse::fromPost($second)],
            new PostsFinder([PostIdMother::DEFAULT_VALUE, 'mi-post', self::OTHER, PostIdMother::DEFAULT_VALUE, self::MISSING])($repository),
        );
    }
}
