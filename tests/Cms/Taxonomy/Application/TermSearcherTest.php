<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermSearcher;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Criteria\InvalidCriteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Searches the terms stored, as the CMS lists them.
 */
final class TermSearcherTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        parent::tearDown();
    }

    public function testItListsTheTermsOfTheTaxonomyNewestFirst(): void
    {
        $older = TermIdMother::random()->value();
        $newer = TermIdMother::random()->value();
        $repository = self::repository();
        $repository->save(TermMother::create(id: $older, name: 'Vida diaria', slug: 'vida-diaria'));
        $repository->save(TermMother::create(id: $newer, name: 'Trabajo', slug: 'trabajo'));
        $repository->save(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));

        $found = new TermSearcher('section', '', 1)($repository);

        self::assertSame([$newer, $older], array_map(static fn (TermResponse $term): string => $term->id, $found->terms));
        self::assertFalse($found->hasMore);
    }

    /**
     * Names are short, as short as an acronym, so any part of one finds it.
     */
    public function testItNarrowsTheTermsDownToThoseWhoseNameHasTheText(): void
    {
        $tea = TermIdMother::random()->value();
        $repository = self::repository();
        $repository->save(TermMother::create(id: $tea, taxonomy: Taxonomy::Tag, name: 'TEA', slug: 'tea'));
        $repository->save(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));

        $found = new TermSearcher('tag', ' te ', 1)($repository);

        self::assertSame([$tea], array_map(static fn (TermResponse $term): string => $term->id, $found->terms));
    }

    public function testItTellsWhetherMoreTermsFollow(): void
    {
        $repository = self::repository();

        for ($i = 0; $i <= TermSearcher::PAGE_SIZE; ++$i) {
            $repository->save(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta '.$i, slug: 'etiqueta-'.$i));
        }

        $first = new TermSearcher('tag', '', 1)($repository);
        $second = new TermSearcher('tag', '', 2)($repository);

        self::assertCount(TermSearcher::PAGE_SIZE, $first->terms);
        self::assertTrue($first->hasMore);
        self::assertCount(1, $second->terms);
        self::assertFalse($second->hasMore);
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new TermSearcher('section', '', 0)(self::repository());
    }

    private static function repository(): TermRepository
    {
        return self::getContainer()->get(TermRepository::class);
    }
}
