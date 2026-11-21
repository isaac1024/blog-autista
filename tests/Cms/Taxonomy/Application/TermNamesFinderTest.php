<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermNamesFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use PHPUnit\Framework\TestCase;

final class TermNamesFinderTest extends TestCase
{
    /**
     * It asks for what can be the id of one, at once, and leaves out the
     * rest without asking: an address or a typo names none.
     */
    public function testItAsksOnlyForWhatCanBeTheIdOfOne(): void
    {
        $id = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('namesOf')->with([new TermId($id)])->willReturn([$id => ['name' => 'Vida diaria', 'taxonomy' => Taxonomy::Section]]);

        self::assertSame([$id => ['name' => 'Vida diaria', 'taxonomy' => 'section']], new TermNamesFinder([$id, 'no-es-un-id', ''])($repository));
    }
}
