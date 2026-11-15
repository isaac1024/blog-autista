<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\InvalidTaxonomy;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermFinderTest extends TestCase
{
    public function testItFindsTheTermOfTheTaxonomyAsTheCmsShowsIt(): void
    {
        $term = TermMother::published(taxonomy: Taxonomy::Tag);
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('find')->with(self::equalTo(TermIdMother::create()))->willReturn($term);

        self::assertEquals(TermResponse::fromTerm($term), new TermFinder('tag', TermIdMother::DEFAULT_VALUE)($repository));
    }

    /**
     * A term of another taxonomy is none of this one.
     */
    public function testItThrowsWhenTheTermIsOfAnotherTaxonomy(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create(taxonomy: Taxonomy::Tag));

        $this->expectExceptionObject(TermNotFound::withId('section', TermIdMother::DEFAULT_VALUE));

        new TermFinder('section', TermIdMother::DEFAULT_VALUE)($repository);
    }

    public function testItThrowsWhenTheTermDoesNotExist(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(TermNotFound::withId('tag', TermIdMother::DEFAULT_VALUE));

        new TermFinder('tag', TermIdMother::DEFAULT_VALUE)($repository);
    }

    public function testItRefusesATaxonomyThatIsNone(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidTaxonomy::unknown('categoria'));

        new TermFinder('categoria', TermIdMother::DEFAULT_VALUE)($repository);
    }
}
