<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Persistence;

use Cms\Shared\Domain\Robots;
use Cms\Taxonomy\Domain\Taxonomy;
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

    private function repository(): TermRepository
    {
        return self::getContainer()->get(TermRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
