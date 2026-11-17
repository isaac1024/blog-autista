<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Application;

use PHPUnit\Framework\TestCase;
use Web\Shared\Domain\Robots;
use Web\Taxonomy\Application\TermFinder;
use Web\Taxonomy\Application\TermNotFound;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class TermFinderTest extends TestCase
{
    public function testItHandsOverTheTermOfTheTaxonomyAtTheSlug(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with(Taxonomy::Tag, 'vida-diaria')
            ->willReturn(Term::publish(CmsTermPublishedMother::create(taxonomy: Taxonomy::Tag, robots: Robots::NoindexFollow)));

        self::assertEquals(
            new TermResponse(TermIdMother::DEFAULT_VALUE, 'tag', 'Vida diaria', 'vida-diaria', 'Artículos sobre la vida diaria.', 'noindex,follow'),
            (new TermFinder('tag', 'vida-diaria'))($repository),
        );
    }

    public function testItThrowsWhenNoTermOfTheTaxonomyIsAtTheSlug(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('findBySlug')->willReturn(null);

        $this->expectExceptionObject(TermNotFound::withSlug('section', 'vida-diaria'));

        (new TermFinder('section', 'vida-diaria'))($repository);
    }
}
