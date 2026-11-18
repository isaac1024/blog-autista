<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermAtAddressFinder;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Shared\Domain\SlugMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermAtAddressFinderTest extends TestCase
{
    public function testItFindsTheTermOfTheTaxonomyTheSiteServesAtTheAddress(): void
    {
        $term = TermMother::published(taxonomy: Taxonomy::Tag);
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('findOnSiteBySlug')->with(Taxonomy::Tag, self::equalTo(SlugMother::create(TermMother::SLUG)))->willReturn($term);

        self::assertEquals(TermResponse::fromTerm($term), new TermAtAddressFinder(Taxonomy::Tag->value, TermMother::SLUG)->onSite($repository));
    }

    public function testItFindsTheTermOffTheSiteWhoseLastAddressItWas(): void
    {
        $term = TermMother::unpublished();
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(Taxonomy::Section, self::equalTo(SlugMother::create(TermMother::SLUG)))->willReturn($term);

        self::assertEquals(TermResponse::fromTerm($term), new TermAtAddressFinder(Taxonomy::Section->value, TermMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneWhereNoTermIs(): void
    {
        $repository = self::createStub(TermRepository::class);

        self::assertNull(new TermAtAddressFinder(Taxonomy::Section->value, TermMother::SLUG)->onSite($repository));
        self::assertNull(new TermAtAddressFinder(Taxonomy::Section->value, TermMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneAtWhatIsNoAddress(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::never())->method('findOnSiteBySlug');
        $repository->expects(self::never())->method('findOffSiteByLastSiteSlug');

        self::assertNull(new TermAtAddressFinder(Taxonomy::Section->value, 'Vida Diaria')->onSite($repository));
        self::assertNull(new TermAtAddressFinder(Taxonomy::Section->value, 'Vida Diaria')->lastOffSite($repository));
    }
}
