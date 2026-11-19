<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;

final class TermsFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a09';

    private const string MISSING = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a08';

    /**
     * Of any taxonomy, all at once: each id once, what is not an id never,
     * in the order asked whatever order the repository answers in, and what
     * nothing has finds none.
     */
    public function testItFindsTheTermsWithTheGivenIdsAsTheCmsShowsThem(): void
    {
        $section = TermMother::create();
        $tag = TermMother::create(id: self::OTHER, taxonomy: Taxonomy::Tag);
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('findByIds')
            ->with(self::equalTo([new TermId(TermIdMother::DEFAULT_VALUE), new TermId(self::OTHER), new TermId(self::MISSING)]))
            ->willReturn([$tag, $section]);

        self::assertEquals(
            [TermResponse::fromTerm($section), TermResponse::fromTerm($tag)],
            new TermsFinder([TermIdMother::DEFAULT_VALUE, 'vida-diaria', self::OTHER, TermIdMother::DEFAULT_VALUE, self::MISSING])($repository),
        );
    }
}
