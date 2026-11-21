<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostTitlesFinder;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use PHPUnit\Framework\TestCase;

final class PostTitlesFinderTest extends TestCase
{
    /**
     * It asks for what can be the id of one, at once, and leaves out the
     * rest without asking: an address or a typo names none.
     */
    public function testItAsksOnlyForWhatCanBeTheIdOfOne(): void
    {
        $id = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('titlesOf')->with([new PostId($id)])->willReturn([$id => 'Mi primer día']);

        self::assertSame([$id => 'Mi primer día'], new PostTitlesFinder([$id, 'no-es-un-id', ''])($repository));
    }
}
