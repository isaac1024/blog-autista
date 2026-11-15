<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermExistenceChecker;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermExistenceCheckerTest extends TestCase
{
    /**
     * A term exists in its own taxonomy alone: a tag is no section.
     */
    public function testItTellsWhetherTheTermExistsInTheTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::exactly(3))
            ->method('find')
            ->with(self::equalTo(TermIdMother::create()))
            ->willReturnOnConsecutiveCalls(TermMother::create(), TermMother::create(taxonomy: Taxonomy::Tag), null);

        self::assertTrue(new TermExistenceChecker(Taxonomy::Section, TermIdMother::DEFAULT_VALUE)($repository));
        self::assertFalse(new TermExistenceChecker(Taxonomy::Section, TermIdMother::DEFAULT_VALUE)($repository));
        self::assertFalse(new TermExistenceChecker(Taxonomy::Section, TermIdMother::DEFAULT_VALUE)($repository));
    }
}
