<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Application;

use PHPUnit\Framework\TestCase;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Application\TermsOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class TermsOnSiteFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    private const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a03';

    /**
     * It asks at once only for ids of terms, and gives the sections and the
     * tags the site has among them, keyed by id, each telling its taxonomy:
     * a term it doesn't have is left out.
     */
    public function testItGivesTheTermsOnTheSiteKeyedById(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([TermIdMother::create(), TermIdMother::create(self::OTHER), TermIdMother::create(self::TAG)]))
            ->willReturn([
                Term::publish(CmsTermPublishedMother::create()),
                Term::publish(CmsTermPublishedMother::create(id: self::TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido')),
            ]);

        self::assertEquals(
            [
                TermIdMother::DEFAULT_VALUE => new TermResponse(TermIdMother::DEFAULT_VALUE, 'section', 'Vida diaria', 'vida-diaria', 'Artículos sobre la vida diaria.', 'index,follow'),
                self::TAG => new TermResponse(self::TAG, 'tag', 'Ruido', 'ruido', 'Artículos sobre la vida diaria.', 'index,follow'),
            ],
            (new TermsOnSiteFinder([TermIdMother::DEFAULT_VALUE, 'vida-diaria', self::OTHER, self::TAG]))($repository),
        );
    }

    public function testItGivesNoTermWhenAskedForNone(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        self::assertSame([], (new TermsOnSiteFinder([]))($repository));
    }
}
