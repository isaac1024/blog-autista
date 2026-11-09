<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\DomainErrorAssertions;
use Web\Analytics\Application\VisitRecorder;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\PublishedPages;
use Web\Analytics\Domain\Visit;
use Web\Analytics\Domain\VisitRepository;

final class VisitRecorderTest extends TestCase
{
    use DomainErrorAssertions;

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e201';
    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    public function testItTellsThePageIsOffTheSite(): void
    {
        self::assertTells('errors.visit.page_not_published', ['%id%' => self::PAGE], fn () => $this->record(published: false));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, int}>
     */
    public static function tooLong(): iterable
    {
        yield 'the page left' => [['referrer' => 'https://example.org/'.str_repeat('a', 2029)], 'referrer', 2048];
        yield 'the campaign source' => [['source' => str_repeat('a', 201)], 'campaign source', 200];
        yield 'the campaign medium' => [['medium' => str_repeat('a', 201)], 'campaign medium', 200];
        yield 'the time zone' => [['zone' => str_repeat('a', 65)], 'time zone', 64];
    }

    /**
     * @param array<string, string> $visit
     */
    #[DataProvider('tooLong')]
    public function testItTellsWhatOfTheVisitIsTooLong(array $visit, string $field, int $max): void
    {
        self::assertTells('errors.visit.too_long', ['%field%' => $field, '%max%' => $max], fn () => $this->record(...$visit));
    }

    /**
     * An address the request can't tell is taken as it comes.
     */
    public function testItKeepsAVisitFromAnAddressItCannotRead(): void
    {
        $visitRepository = $this->createMock(VisitRepository::class);
        $visitRepository->expects(self::once())->method('save')->with(self::isInstanceOf(Visit::class));

        $this->record(address: 'unknown', visitRepository: $visitRepository);
    }

    private function record(
        string $referrer = '',
        string $source = '',
        string $medium = '',
        string $zone = '',
        string $address = '203.0.113.1',
        bool $published = true,
        ?VisitRepository $visitRepository = null,
    ): void {
        $publishedPages = self::createStub(PublishedPages::class);
        $publishedPages->method('contains')->willReturn($published);
        $dailySalt = self::createStub(DailySalt::class);
        $dailySalt->method('forDay')->willReturn('salt');

        new VisitRecorder(self::PAGE, $referrer, $source, $medium, $zone, $address, self::BROWSER, ['www.blogautista.localhost'])(
            $visitRepository ?? self::createStub(VisitRepository::class),
            $publishedPages,
            $dailySalt,
        );
    }
}
