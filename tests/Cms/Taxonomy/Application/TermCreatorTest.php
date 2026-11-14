<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermCreator;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TermCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the terms compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validInputs')]
    public function testItPersistsTheTermBuiltFromValidInput(string $taxonomy, string $name, Term $expected): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new TermCreator(TermIdMother::DEFAULT_VALUE, $taxonomy, $name)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, Term}>
     */
    public static function validInputs(): iterable
    {
        yield 'a section, its slug derived from the name' => ['section', 'La vida diaria', TermMother::create(name: 'La vida diaria', slug: 'vida-diaria')];
        yield 'a tag' => ['tag', 'Sensorialidad', TermMother::create(taxonomy: Taxonomy::Tag, name: 'Sensorialidad', slug: 'sensorialidad')];
        yield 'surrounding whitespace is trimmed' => ['tag', '  Sueño  ', TermMother::create(taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno')];
        yield 'shortest name' => ['tag', 'TEA', TermMother::create(taxonomy: Taxonomy::Tag, name: 'TEA', slug: 'tea')];
        yield 'longest name' => ['tag', str_repeat('a', 60), TermMother::create(taxonomy: Taxonomy::Tag, name: str_repeat('a', 60), slug: str_repeat('a', 60))];

        // Only sections are served at the first level of the site.
        yield 'a tag named as a path the site reserves' => ['tag', 'Autor', TermMother::create(taxonomy: Taxonomy::Tag, name: 'Autor', slug: 'autor')];
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidInputs')]
    public function testItPersistsNothingWhenTheInputIsInvalid(string $taxonomy, string $name, string $key, array $parameters): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => new TermCreator(TermIdMother::DEFAULT_VALUE, $taxonomy, $name)($repository, self::createStub(EventBus::class)));
    }

    /**
     * @return iterable<string, array{string, string, string, array<string, int|string>}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'unknown taxonomy' => ['author', 'Isaac', 'errors.term.taxonomy.unknown', ['%taxonomy%' => 'author']];
        yield 'name too short' => ['section', 'Ab', 'errors.term.name.too_short', ['%min%' => 3]];
        yield 'name too long' => ['section', str_repeat('a', 61), 'errors.term.name.too_long', ['%max%' => 60]];
        yield 'section at the address of the authors' => ['section', 'Autor', 'errors.term.slug.reserved', ['%slug%' => 'autor']];
        yield 'section at the address of the tags' => ['section', 'Tag', 'errors.term.slug.reserved', ['%slug%' => 'tag']];
        yield 'section at the address of the images' => ['section', 'Media', 'errors.term.slug.reserved', ['%slug%' => 'media']];
        yield 'name without sluggable characters' => ['tag', '¡¡!!', 'errors.slug.malformed', ['%slug%' => '']];
    }

    public function testItRefusesTheSlugOfAnotherTermOfTheTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('findBySlug')->willReturnCallback(
            static fn (Taxonomy $taxonomy): ?Term => Taxonomy::Tag === $taxonomy ? TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag) : null,
        );
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.term.slug.taken', ['%slug%' => 'vida-diaria'], static fn () => new TermCreator(TermIdMother::DEFAULT_VALUE, 'tag', 'Vida diaria')($repository, self::createStub(EventBus::class)));
    }

    /**
     * Each taxonomy serves its terms at addresses of its own.
     */
    public function testItTakesASlugATermOfAnotherTaxonomyHas(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('findBySlug')->willReturnCallback(
            static fn (Taxonomy $taxonomy): ?Term => Taxonomy::Tag === $taxonomy ? TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag) : null,
        );
        $repository->expects(self::once())->method('save');

        new TermCreator(TermIdMother::DEFAULT_VALUE, 'section', 'Vida diaria')($repository, self::createStub(EventBus::class));
    }

    public function testItTellsNothingOfADraft(): void
    {
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with();

        new TermCreator(TermIdMother::DEFAULT_VALUE, 'section', 'Vida diaria')(self::createStub(TermRepository::class), $eventBus);
    }
}
