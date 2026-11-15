<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Persistence;

use Cms\Shared\Domain\Robots;
use Cms\Taxonomy\Domain\NameFilter;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyFilter;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermSlugAlreadyTaken;
use Cms\Tests\Shared\Domain\SlugMother;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTermRepositoryTest extends KernelTestCase
{
    use MappedTables;

    #[DataProvider('terms')]
    public function testItSavesTheTermSoItCanBeReadBack(Term $term): void
    {
        $this->repository()->save($term);
        $this->entityManager()->clear();

        self::assertEquals($term, $this->repository()->find($term->id()));
    }

    /**
     * @return iterable<string, array{Term}>
     */
    public static function terms(): iterable
    {
        yield 'a draft section' => [TermMother::create(id: TermIdMother::random()->value())];
        yield 'a tag without a slug, kept from search engines' => [TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: '', metaDescription: 'Sobre el sueño', robots: Robots::NoindexNofollow)];
        yield 'a published term with changes' => [TermMother::published(id: TermIdMother::random()->value(), name: 'Día a día', publishedName: 'Vida diaria', publishedSlug: 'vida')];
        yield 'an unpublished term' => [TermMother::unpublished(id: TermIdMother::random()->value())];
    }

    public function testItFindsNoTermWhenTheIdDoesNotExist(): void
    {
        self::assertNull($this->repository()->find(TermIdMother::random()));
    }

    public function testItFindsTheTermOfTheTaxonomyEditedOrOnTheSiteWithTheSlug(): void
    {
        $id = TermIdMother::random()->value();
        $this->repository()->save(TermMother::published(id: $id, slug: 'dia-a-dia', publishedSlug: 'vida-diaria'));
        $this->entityManager()->clear();

        self::assertSame($id, $this->repository()->findBySlug(Taxonomy::Section, SlugMother::create('dia-a-dia'))?->id()->value());
        self::assertSame($id, $this->repository()->findBySlug(Taxonomy::Section, SlugMother::create('vida-diaria'))?->id()->value());
        self::assertNull($this->repository()->findBySlug(Taxonomy::Tag, SlugMother::create('dia-a-dia')));
    }

    public function testItFindsTheTermOfTheTaxonomyOnTheSiteWithTheSlugOnlyThere(): void
    {
        $id = TermIdMother::random()->value();
        $this->repository()->save(TermMother::published(id: $id, slug: 'dia-a-dia', publishedSlug: 'vida-diaria'));
        $this->entityManager()->clear();

        self::assertSame($id, $this->repository()->findOnSiteBySlug(Taxonomy::Section, SlugMother::create('vida-diaria'))?->id()->value());
        self::assertNull($this->repository()->findOnSiteBySlug(Taxonomy::Section, SlugMother::create('dia-a-dia')));
        self::assertNull($this->repository()->findOnSiteBySlug(Taxonomy::Tag, SlugMother::create('vida-diaria')));
    }

    /**
     * Off the site, a term is found by the slug it last had there, and the
     * newest one when two did; one of another taxonomy, or on the site, never.
     */
    public function testItFindsTheTermOffTheSiteThatLastHadASlug(): void
    {
        $older = TermMother::unpublished(id: '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0d01');
        $older->edit(TermMother::NAME, 'antigua', TermMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($older);
        $this->repository()->save(TermMother::unpublished(id: '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0d02'));
        $this->repository()->save(TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: 'sueno'));
        $this->entityManager()->clear();

        self::assertSame('01a1da50-2b7c-7d01-8a3f-5c2e9b1d0d02', $this->repository()->findOffSiteByLastSiteSlug(Taxonomy::Section, SlugMother::create(TermMother::SLUG))?->id()->value());
        self::assertNull($this->repository()->findOffSiteByLastSiteSlug(Taxonomy::Tag, SlugMother::create(TermMother::SLUG)));
        self::assertNull($this->repository()->findOffSiteByLastSiteSlug(Taxonomy::Tag, SlugMother::create('sueno')));
    }

    public function testItRejectsSavingATermWithTheSlugOfAnotherOfTheTaxonomy(): void
    {
        $this->repository()->save(TermMother::create(id: TermIdMother::random()->value()));

        $this->expectExceptionObject(TermSlugAlreadyTaken::bySlug(SlugMother::create(TermMother::SLUG)));

        $this->repository()->save(TermMother::create(id: TermIdMother::random()->value(), name: 'Otra'));
    }

    public function testItRejectsSavingATermWithTheSlugAnotherOfTheTaxonomyHasOnTheSite(): void
    {
        $this->repository()->save(TermMother::published(id: TermIdMother::random()->value(), slug: 'otra', publishedSlug: TermMother::SLUG));

        $this->expectExceptionObject(TermSlugAlreadyTaken::bySlug(SlugMother::create(TermMother::SLUG)));

        $this->repository()->save(TermMother::published(id: TermIdMother::random()->value(), slug: 'otra-mas', publishedSlug: TermMother::SLUG));
    }

    public function testItKeepsTermsOfEachTaxonomyApart(): void
    {
        $this->repository()->save(TermMother::published(id: TermIdMother::random()->value()));
        $this->repository()->save(TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag));
        $this->repository()->save(TermMother::create(id: TermIdMother::random()->value(), slug: ''));
        $this->repository()->save(TermMother::create(id: TermIdMother::random()->value(), slug: ''));

        self::assertCount(4, $this->repository()->matching(new Criteria(limit: 10, filters: [], order: null, offset: 0)));
    }

    /**
     * Newest first, of a taxonomy, and narrowed down to those whose name has
     * the text, as short as an acronym, in any case.
     */
    public function testItFindsTheTermsMatchingTheCriteria(): void
    {
        $older = TermIdMother::random()->value();
        $newer = TermIdMother::random()->value();
        $tea = TermIdMother::random()->value();
        $this->repository()->save(TermMother::create(id: $older, name: 'Vida diaria', slug: 'vida-diaria'));
        $this->repository()->save(TermMother::create(id: $newer, name: 'Trabajo', slug: 'trabajo'));
        $this->repository()->save(TermMother::create(id: $tea, taxonomy: Taxonomy::Tag, name: 'TEA', slug: 'tea'));
        $this->entityManager()->clear();
        $newestFirst = new Order('id', OrderDirection::Descending);

        self::assertSame([$newer, $older], self::ids($this->repository()->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [TaxonomyFilter::equalTo(Taxonomy::Section)], order: $newestFirst, offset: 0))));
        self::assertSame([$tea], self::ids($this->repository()->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [TaxonomyFilter::equalTo(Taxonomy::Tag), NameFilter::contains('te')], order: $newestFirst, offset: 0))));
        self::assertSame([$older], self::ids($this->repository()->matching(new Criteria(limit: 1, filters: [TaxonomyFilter::equalTo(Taxonomy::Section)], order: $newestFirst, offset: 1))));
    }

    public function testItDeletesOnlyTheGivenTerm(): void
    {
        $kept = TermMother::create(id: TermIdMother::random()->value(), slug: 'otra');
        $deleted = TermMother::create(id: TermIdMother::random()->value());
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->find($deleted->id()));
        self::assertNotNull($this->repository()->find($kept->id()));
    }

    /**
     * A column the database holds corrupt is refused, never read as
     * something else. The database itself refuses what is no JSON.
     */
    #[DataProvider('corruptVersions')]
    public function testItRefusesToReadAVersionTheDatabaseHoldsCorrupt(string $value): void
    {
        $term = TermMother::published(id: TermIdMother::random()->value());
        $this->repository()->save($term);
        $this->entityManager()->getConnection()->executeStatement('UPDATE cms_term SET published_version = ? WHERE id = ?', [$value, $term->id()->value()]);
        $this->entityManager()->clear();

        $this->expectException(ValueNotConvertible::class);

        $this->repository()->find($term->id());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function corruptVersions(): iterable
    {
        yield 'a version that is no object' => ['"texto"'];
        yield 'a version without its name' => ['{"robots": "index,follow"}'];
        yield 'a version without its robots' => ['{"name": "Vida diaria"}'];
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('cms_term');
    }

    /**
     * @param list<Term> $terms
     *
     * @return list<string>
     */
    private static function ids(array $terms): array
    {
        return array_map(static fn (Term $term): string => $term->id()->value(), $terms);
    }

    private function repository(): TermRepository
    {
        return self::getContainer()->get(TermRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
