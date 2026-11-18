<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Shared\Domain\Robots;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermUpdater;
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
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TermUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validEdits')]
    public function testItSavesTheTermWithTheFieldsEdited(Term $term, string $name, string $slug, string $metaDescription, string $robots, Term $expected): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new TermUpdater($term->taxonomy()->value, $term->id()->value(), $name, $slug, $metaDescription, $robots)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{Term, string, string, string, string, Term}>
     */
    public static function validEdits(): iterable
    {
        yield 'every field of a draft, its update dated' => [
            TermMother::create(updatedAt: self::BEFORE),
            'Día a día',
            'dia-a-dia',
            '  Artículos   sobre el día a día ',
            'noindex,follow',
            TermMother::create(name: 'Día a día', slug: 'dia-a-dia', metaDescription: 'Artículos sobre el día a día', robots: Robots::NoindexFollow),
        ];
        yield 'a blank slug and meta description leave it without them' => [
            TermMother::create(metaDescription: TermMother::META_DESCRIPTION),
            TermMother::NAME,
            '',
            '   ',
            'index,follow',
            TermMother::create(slug: '', metaDescription: null),
        ];
        yield 'nothing changed keeps its update date' => [
            TermMother::create(updatedAt: self::BEFORE),
            TermMother::NAME,
            TermMother::SLUG,
            '',
            'index,follow',
            TermMother::create(updatedAt: self::BEFORE),
        ];
        yield 'a published term keeps its version on the site' => [
            TermMother::published(),
            'Día a día',
            TermMother::SLUG,
            TermMother::META_DESCRIPTION,
            'index,follow',
            TermMother::published(name: 'Día a día', publishedName: TermMother::NAME),
        ];
        yield 'a tag at a path only sections never take' => [
            TermMother::create(taxonomy: Taxonomy::Tag),
            'Media',
            'media',
            '',
            'index,follow',
            TermMother::create(taxonomy: Taxonomy::Tag, name: 'Media', slug: 'media'),
        ];
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidEdits')]
    public function testItSavesNothingWhenAnEditIsInvalid(string $name, string $slug, string $metaDescription, string $robots, string $key, array $parameters): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create());
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => new TermUpdater('section', TermIdMother::DEFAULT_VALUE, $name, $slug, $metaDescription, $robots)($repository, self::createStub(EventBus::class), new RecordingTransaction()));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, array<string, int|string>}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'name too short' => ['Ab', TermMother::SLUG, '', 'index,follow', 'errors.term.name.too_short', ['%min%' => 3]];
        yield 'malformed slug' => [TermMother::NAME, 'Vida-Diaria', '', 'index,follow', 'errors.slug.malformed', ['%slug%' => 'Vida-Diaria']];
        yield 'reserved slug of a section' => [TermMother::NAME, 'tag', '', 'index,follow', 'errors.term.slug.reserved', ['%slug%' => 'tag']];
        yield 'meta description too long' => [TermMother::NAME, TermMother::SLUG, str_repeat('a', 201), 'index,follow', 'errors.seo.description_too_long', ['%max%' => 200]];
        yield 'unknown robots directive' => [TermMother::NAME, TermMother::SLUG, '', 'noindex', 'errors.seo.unknown_robots', ['%robots%' => 'noindex']];
    }

    public function testItRefusesTheSlugOfAnotherTermOfTheTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create());
        $repository->method('findBySlug')->willReturn(TermMother::create(id: TermIdMother::random()->value(), slug: 'otra'));
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.term.slug.taken', ['%slug%' => 'otra'], static fn () => new TermUpdater('section', TermIdMother::DEFAULT_VALUE, TermMother::NAME, 'otra', '', 'index,follow')($repository, self::createStub(EventBus::class), new RecordingTransaction()));
    }

    public function testItKeepsItsOwnSlug(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create());
        $repository->method('findBySlug')->willReturn(TermMother::create());
        $repository->expects(self::once())->method('save');

        new TermUpdater('section', TermIdMother::DEFAULT_VALUE, 'Día a día', TermMother::SLUG, '', 'index,follow')($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * A tag isn't edited as a section, nor the other way round.
     */
    public function testItThrowsWhenTheTermIsOfAnotherTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create(taxonomy: Taxonomy::Tag));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(TermNotFound::withId('section', TermIdMother::DEFAULT_VALUE));

        new TermUpdater('section', TermIdMother::DEFAULT_VALUE, TermMother::NAME, TermMother::SLUG, '', 'index,follow')($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    public function testItThrowsWhenTheTermDoesNotExist(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(TermNotFound::withId('tag', TermIdMother::DEFAULT_VALUE));

        new TermUpdater('tag', TermIdMother::DEFAULT_VALUE, TermMother::NAME, TermMother::SLUG, '', 'index,follow')($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * Saving it and telling what it uses happen within one transaction, so
     * a subscriber told at once that fails undoes the save.
     */
    public function testItSavesTheTermAndTellsWhatChangedWithinOneTransaction(): void
    {
        $transaction = new RecordingTransaction();
        $within = [];
        $note = static function () use ($transaction, &$within): void {
            $within[] = $transaction->isRunning();
        };
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create());
        $repository->method('save')->willReturnCallback($note);
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback($note);

        new TermUpdater('section', TermIdMother::DEFAULT_VALUE, 'Día a día', TermMother::SLUG, '', 'index,follow')($repository, $eventBus, $transaction);

        self::assertSame([true, true], $within);
    }
}
