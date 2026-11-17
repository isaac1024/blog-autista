<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Shared\Domain\Robots;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class DoctrineTermRepositoryTest extends KernelTestCase
{
    use MappedTables;

    public function testItSavesTheTermSoItCanBeReadBack(): void
    {
        $term = Term::publish(CmsTermPublishedMother::create(taxonomy: Taxonomy::Tag, metaDescription: null, robots: Robots::NoindexNofollow));

        $this->repository()->save($term);
        $this->entityManager()->clear();

        self::assertEquals($term, $this->repository()->find(TermIdMother::create()));
    }

    /**
     * A section and a tag may share a slug: each taxonomy has its own.
     */
    public function testItFindsTheTermAtTheSlugInItsTaxonomy(): void
    {
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create()));
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta')));
        $this->entityManager()->clear();

        self::assertSame('Vida diaria', $this->repository()->findBySlug(Taxonomy::Section, 'vida-diaria')?->name());
        self::assertSame('Etiqueta', $this->repository()->findBySlug(Taxonomy::Tag, 'vida-diaria')?->name());
        self::assertNull($this->repository()->findBySlug(Taxonomy::Section, 'dia-a-dia'));
    }

    /**
     * The terms among those asked for, of any taxonomy, in one query; those
     * it doesn't have are left out.
     */
    public function testItFindsTheTermsAmongThoseGiven(): void
    {
        $tag = TermIdMother::random();
        $missing = TermIdMother::random();
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create()));
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create(id: $tag->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta')));
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create(id: TermIdMother::random()->value(), slug: 'dia-a-dia', name: 'No pedida')));
        $this->entityManager()->clear();

        $names = array_map(static fn (Term $term): string => $term->name(), $this->repository()->findByIds([$missing, $tag, TermIdMother::create()]));
        sort($names);

        self::assertSame(['Etiqueta', 'Vida diaria'], $names);
        self::assertSame([], $this->repository()->findByIds([]));
    }

    /**
     * A term already read is handed over as read, beside those it reads.
     */
    public function testItFindsTheTermsAlreadyReadWithThoseItReads(): void
    {
        $tag = TermIdMother::random();
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create()));
        $this->repository()->save(Term::publish(CmsTermPublishedMother::create(id: $tag->value(), taxonomy: Taxonomy::Tag, name: 'Etiqueta')));
        $this->entityManager()->clear();
        $read = $this->repository()->find(TermIdMother::create());

        $terms = $this->repository()->findByIds([TermIdMother::create(), $tag]);

        self::assertCount(2, $terms);
        self::assertSame($read, $terms[0]);
        self::assertSame('Etiqueta', $terms[1]->name());
    }

    public function testItDeletesOnlyTheGivenTerm(): void
    {
        $kept = Term::publish(CmsTermPublishedMother::create(id: TermIdMother::random()->value(), slug: 'dia-a-dia'));
        $deleted = Term::publish(CmsTermPublishedMother::create());
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->findBySlug(Taxonomy::Section, 'vida-diaria'));
        self::assertNotNull($this->repository()->findBySlug(Taxonomy::Section, 'dia-a-dia'));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_term');
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
