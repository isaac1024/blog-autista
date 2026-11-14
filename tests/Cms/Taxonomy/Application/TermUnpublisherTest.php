<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermUnpublisher;
use Cms\Taxonomy\Domain\InvalidTermPublication;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermUnpublished;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;

final class TermUnpublisherTest extends TestCase
{
    public function testItTakesThePublishedTermOffTheSite(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published());
        $repository->expects(self::once())->method('save')->with(self::equalTo(TermMother::unpublished()));

        new TermUnpublisher('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));
    }

    public function testItTellsTheSiteToTakeItOff(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof TermUnpublished
                && 'cms.term.unpublished' === $event::eventName()
                && TermIdMother::DEFAULT_VALUE === $event->aggregateId()
                && ['revision' => 2] === $event->toPrimitives(),
        ));

        new TermUnpublisher('section', TermIdMother::DEFAULT_VALUE)($repository, $eventBus);
    }

    #[DataProvider('termsOffTheSite')]
    public function testItRefusesATermOffTheSite(Term $term): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidTermPublication::notPublished());

        new TermUnpublisher('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Term}>
     */
    public static function termsOffTheSite(): iterable
    {
        yield 'a draft' => [TermMother::create()];
        yield 'an unpublished term' => [TermMother::unpublished()];
    }
}
