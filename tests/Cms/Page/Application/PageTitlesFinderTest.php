<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageTitlesFinder;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use PHPUnit\Framework\TestCase;

final class PageTitlesFinderTest extends TestCase
{
    /**
     * It asks for what can be the id of one, at once, and leaves out the
     * rest without asking: an address or a typo names none.
     */
    public function testItAsksOnlyForWhatCanBeTheIdOfOne(): void
    {
        $id = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('titlesOf')->with([new PageId($id)])->willReturn([$id => 'Sobre mí']);

        self::assertSame([$id => 'Sobre mí'], new PageTitlesFinder([$id, 'no-es-un-id', ''])($repository));
    }
}
