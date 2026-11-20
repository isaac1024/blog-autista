<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\TestCase;
use Web\Post\Application\LatestPostIdsFinder;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

final class LatestPostIdsFinderTest extends TestCase
{
    /**
     * It asks for the first ones of the selection, as many as it was given,
     * and hands their ids over in the order the repository reads them.
     */
    public function testItHandsOverTheIdsOfTheLatestPostsOfTheSelection(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('listedIds')
            ->with(new PostSelection('01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01', '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02', ['01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02']), 0, 6)
            ->willReturn([new PostId('01a1e000-0000-7000-8000-000000000002'), new PostId('01a1e000-0000-7000-8000-000000000001')]);

        self::assertSame(
            ['01a1e000-0000-7000-8000-000000000002', '01a1e000-0000-7000-8000-000000000001'],
            new LatestPostIdsFinder('01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01', '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02', ['01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02'], 6)($repository),
        );
    }
}
