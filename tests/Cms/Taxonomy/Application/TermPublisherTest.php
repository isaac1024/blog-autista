<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermPublisher;
use Cms\Taxonomy\Domain\InvalidTermPublication;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermPublished;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Shared\Domain\SlugMother;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TermPublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishableTerms')]
    public function testItPutsTheSavedVersionOnTheSite(Term $term, Term $expected): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new TermPublisher($term->taxonomy()->value, $term->id()->value())($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Term, Term}>
     */
    public static function publishableTerms(): iterable
    {
        yield 'a draft' => [
            TermMother::create(metaDescription: TermMother::META_DESCRIPTION, updatedAt: self::BEFORE),
            TermMother::published(updatedAt: self::BEFORE),
        ];
        yield 'a published term with changes, keeping its first publication' => [
            TermMother::published(name: 'Día a día', publishedName: TermMother::NAME, firstPublishedAt: self::BEFORE, lastPublishedAt: self::BEFORE),
            TermMother::published(name: 'Día a día', firstPublishedAt: self::BEFORE, publicationRevision: 2),
        ];
        yield 'an unpublished term' => [
            TermMother::unpublished(),
            TermMother::published(publicationRevision: 3),
        ];
    }

    public function testItTellsTheSiteWhatToServe(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published(
            taxonomy: Taxonomy::Tag,
            name: 'Sensorialidad',
            slug: 'sensorialidad',
            publishedName: 'Sentidos',
            firstPublishedAt: self::BEFORE,
            lastPublishedAt: self::BEFORE,
        ));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof TermPublished
                && TermIdMother::DEFAULT_VALUE === $event->aggregateId()
                && 'cms.term.published' === $event::eventName()
                && [
                    'taxonomy' => 'tag',
                    'name' => 'Sensorialidad',
                    'slug' => 'sensorialidad',
                    'meta_description' => TermMother::META_DESCRIPTION,
                    'robots' => 'index,follow',
                    'first_published_at' => ClockMother::now(self::BEFORE)->format(\DATE_ATOM),
                    'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
                    'revision' => 2,
                ] === $event->toPrimitives(),
        ));

        new TermPublisher('tag', TermIdMother::DEFAULT_VALUE)($repository, $eventBus);
    }

    #[DataProvider('unpublishableTerms')]
    public function testItPublishesNothingWithoutEverythingTheSiteShows(Term $term, DomainError $error): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $this->expectExceptionObject($error);

        new TermPublisher('section', $term->id()->value())($repository, $eventBus);
    }

    /**
     * @return iterable<string, array{Term, DomainError}>
     */
    public static function unpublishableTerms(): iterable
    {
        yield 'a draft without a slug' => [TermMother::create(slug: '', metaDescription: TermMother::META_DESCRIPTION), InvalidTermPublication::withoutSlug()];
        yield 'a draft without a meta description' => [TermMother::create(), InvalidTermPublication::withoutMetaDescription()];
        yield 'a published term saved without a meta description' => [TermMother::published(metaDescription: null), InvalidTermPublication::withoutMetaDescription()];
        yield 'a published term as it is on the site' => [TermMother::published(), InvalidTermPublication::nothingToPublish()];
    }

    /**
     * Published at the address a term of its taxonomy off the site last had,
     * it takes it from that one, which is saved without it.
     */
    public function testItTakesTheLastAddressOfTheTermOffTheSiteThatHadIt(): void
    {
        $former = TermMother::unpublished(id: TermIdMother::random()->value());
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create(metaDescription: TermMother::META_DESCRIPTION));
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(Taxonomy::Section, self::equalTo(SlugMother::create(TermMother::SLUG)))->willReturn($former);
        $repository->expects(self::exactly(2))->method('save');

        new TermPublisher('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));

        self::assertNull($former->lastSiteSlug());
    }

    public function testItKeepsItsOwnLastAddress(): void
    {
        $term = TermMother::published(publishedName: 'Antes');
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->method('findOffSiteByLastSiteSlug')->willReturn($term);
        $repository->expects(self::once())->method('save');

        new TermPublisher('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));

        self::assertSame(TermMother::SLUG, $term->lastSiteSlug()?->value());
    }

    public function testItThrowsWhenTheTermDoesNotExist(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(TermNotFound::withId('section', TermIdMother::DEFAULT_VALUE));

        new TermPublisher('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));
    }
}
