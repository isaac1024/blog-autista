<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Application;

use PHPUnit\Framework\TestCase;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class TermOnSiteFinderTest extends TestCase
{
    public function testItHandsOverTheTermWhenItIsOnTheSiteInTheTaxonomy(): void
    {
        $term = Term::publish(CmsTermPublishedMother::create());
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(TermIdMother::create()))
            ->willReturn($term);

        self::assertEquals(TermResponse::fromTerm($term), (new TermOnSiteFinder('section', TermIdMother::DEFAULT_VALUE))($repository));
    }

    public function testItFindsNothingWhenTheTermIsOfAnotherTaxonomy(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(Term::publish(CmsTermPublishedMother::create(taxonomy: Taxonomy::Tag)));

        self::assertNull((new TermOnSiteFinder('section', TermIdMother::DEFAULT_VALUE))($repository));
    }

    public function testItFindsNothingWhenTheTermIsNotOnTheSite(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(null);

        self::assertNull((new TermOnSiteFinder('section', TermIdMother::DEFAULT_VALUE))($repository));
    }
}
