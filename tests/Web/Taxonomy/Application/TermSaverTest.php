<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Application;

use PHPUnit\Framework\TestCase;
use Web\Taxonomy\Application\TermSaver;
use Web\Taxonomy\Domain\SlugTaken;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class TermSaverTest extends TestCase
{
    public function testItPutsANewTermOnTheSite(): void
    {
        $event = CmsTermPublishedMother::create();
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(TermIdMother::create()))
            ->willReturn(null);
        $repository->method('findBySlug')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Term::publish($event)));

        (new TermSaver($event))($repository);
    }

    public function testItReplacesTheTermOnTheSiteWithTheVersionPublishedLater(): void
    {
        $later = CmsTermPublishedMother::create(name: 'Día a día', slug: 'dia-a-dia', metaDescription: null, lastPublishedAt: '2026-11-08 11:00:00');
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(Term::publish(CmsTermPublishedMother::create()));
        $repository->method('findBySlug')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Term::publish($later)));

        (new TermSaver($later))($repository);
    }

    public function testItLooksForTheSlugAmongTheTermsOfItsTaxonomyAlone(): void
    {
        $event = CmsTermPublishedMother::create(taxonomy: Taxonomy::Tag);
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::once())->method('findBySlug')->with(Taxonomy::Tag, 'vida-diaria')->willReturn(null);
        $repository->expects(self::once())->method('save');

        (new TermSaver($event))($repository);
    }

    public function testItRefusesASlugAnotherTermOfTheTaxonomyStillHas(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->method('findBySlug')->willReturn(Term::publish(CmsTermPublishedMother::create(id: TermIdMother::random()->value())));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(SlugTaken::withSlug('vida-diaria'));

        (new TermSaver(CmsTermPublishedMother::create()))($repository);
    }
}
