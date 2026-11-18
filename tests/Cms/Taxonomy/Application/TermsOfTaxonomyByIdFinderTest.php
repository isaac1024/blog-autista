<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermsOfTaxonomyByIdFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a09';

    private const string MISSING = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a08';

    /**
     * In the order given, those of the taxonomy alone: a section among the
     * tags, an id nothing has and what is not an id find none.
     */
    public function testItFindsTheTermsOfTheTaxonomyAmongTheIdsGiven(): void
    {
        $tag = TermMother::create(id: self::OTHER, taxonomy: Taxonomy::Tag);
        $terms = [TermIdMother::DEFAULT_VALUE => TermMother::create(), self::OTHER => $tag];
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => $terms[$id->value()] ?? null);

        self::assertEquals(
            [TermResponse::fromTerm($tag)],
            new TermsOfTaxonomyByIdFinder(Taxonomy::Tag->value, [TermIdMother::DEFAULT_VALUE, 'etiqueta', self::MISSING, self::OTHER])($repository),
        );
    }
}
