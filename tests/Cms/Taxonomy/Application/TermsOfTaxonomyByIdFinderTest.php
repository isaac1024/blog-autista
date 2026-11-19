<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermsOfTaxonomyByIdFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a09';

    private const string MISSING = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a08';

    private const string OTHER_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a07';

    /**
     * In the order given, those of the taxonomy alone: a section among the
     * tags, an id nothing has and what is not an id find none.
     */
    public function testItFindsTheTermsOfTheTaxonomyAmongTheIdsGiven(): void
    {
        $tag = TermMother::create(id: self::OTHER, taxonomy: Taxonomy::Tag);
        $other = TermMother::create(id: self::OTHER_TAG, taxonomy: Taxonomy::Tag);
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('findByIds')
            ->with(self::equalTo([new TermId(TermIdMother::DEFAULT_VALUE), new TermId(self::MISSING), new TermId(self::OTHER), new TermId(self::OTHER_TAG)]))
            ->willReturn([$other, TermMother::create(), $tag]);

        self::assertEquals(
            [TermResponse::fromTerm($tag), TermResponse::fromTerm($other)],
            new TermsOfTaxonomyByIdFinder(Taxonomy::Tag->value, [TermIdMother::DEFAULT_VALUE, 'etiqueta', self::MISSING, self::OTHER, self::OTHER_TAG])($repository),
        );
    }
}
