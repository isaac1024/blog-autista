<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermChangesDiscarder;
use Cms\Taxonomy\Domain\InvalidTermPublication;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TermChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItBringsTheTermBackToTheVersionOnTheSite(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published(name: 'Día a día', publishedName: TermMother::NAME, updatedAt: '2026-10-01 09:00:00'));
        $repository->expects(self::once())->method('save')->with(self::equalTo(TermMother::published()));

        new TermChangesDiscarder('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    #[DataProvider('termsWithoutChanges')]
    public function testItRefusesATermWithNoChangesToDiscard(Term $term): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidTermPublication::noChangesToDiscard());

        new TermChangesDiscarder('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{Term}>
     */
    public static function termsWithoutChanges(): iterable
    {
        yield 'a draft' => [TermMother::create()];
        yield 'a published term as it is on the site' => [TermMother::published()];
        yield 'an unpublished term, edited or not' => [TermMother::unpublished()];
    }
}
