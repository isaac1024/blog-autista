<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Analytics\Application\VisitRecorder;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\PublishedPages;
use Web\Analytics\Domain\VisitId;
use Web\Analytics\Domain\VisitRepository;
use Web\Analytics\Infrastructure\Scheduler\NightlyVisitCount;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

final class NightlyVisitCountTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e101';
    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105e102';
    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';
    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1';
    private const string SITE = 'www.blogautista.localhost';

    /**
     * Every dimension counts its own readers: two pages seen by one reader
     * are two views of one reader for the site, and one view each for the
     * pages. A source counts only readers who entered through it, and an
     * unknown country counts under no value.
     */
    public function testItCountsTheVisitsOfADayByEveryDimensionAndForgetsThem(): void
    {
        self::bootKernel();
        $this->publish(self::PAGE, self::OTHER_PAGE);
        self::mockTime('2026-11-07 10:00:00');
        $this->visit(self::PAGE, address: '203.0.113.1', referrer: 'https://www.google.es/', zone: 'Europe/Madrid');
        $this->visit(self::OTHER_PAGE, address: '203.0.113.1', referrer: 'http://'.self::SITE.'/sobre-mi.html', zone: 'Europe/Madrid');
        $this->visit(self::PAGE, address: '203.0.113.2', userAgent: self::PHONE, referrer: 'https://www.google.es/', zone: 'America/Mexico_City');
        $this->visit(self::PAGE, address: '203.0.113.3', zone: 'UTC');

        self::mockTime('2026-11-08 00:30:00 Europe/Madrid');
        $this->countTonight();

        self::assertSame([
            ['channel', 'direct', 1, 1],
            ['channel', 'internal', 1, 1],
            ['channel', 'search', 2, 2],
            ['country', '', 1, 1],
            ['country', 'ES', 2, 1],
            ['country', 'MX', 1, 1],
            ['device', 'desktop', 3, 2],
            ['device', 'mobile', 1, 1],
            ['page', self::PAGE, 3, 3],
            ['page', self::OTHER_PAGE, 1, 1],
            ['site', '', 4, 3],
            ['source', 'google.es', 2, 2],
        ], $this->totals('2026-11-07'));
        self::assertSame(0, $this->visitsLeft());
    }

    public function testItCountsEveryDayLeftUncountedButToday(): void
    {
        self::bootKernel();
        $this->publish(self::PAGE);
        foreach (['2026-11-05 12:00:00', '2026-11-06 12:00:00', '2026-11-07 12:00:00'] as $now) {
            self::mockTime($now);
            $this->visit(self::PAGE, address: '203.0.113.1');
        }

        $this->countTonight();

        self::assertSame([['site', '', 1, 1]], $this->siteTotals('2026-11-05'));
        self::assertSame([['site', '', 1, 1]], $this->siteTotals('2026-11-06'));
        self::assertSame([], $this->siteTotals('2026-11-07'));
        self::assertSame(1, $this->visitsLeft());
    }

    /**
     * A day counted is forgotten with its visits, so counting again counts
     * nothing twice.
     */
    public function testCountingAgainCountsNothingTwice(): void
    {
        self::bootKernel();
        $this->publish(self::PAGE);
        self::mockTime('2026-11-06 12:00:00');
        $this->visit(self::PAGE, address: '203.0.113.1');
        $this->visit(self::PAGE, address: '203.0.113.2');
        self::mockTime('2026-11-07 12:00:00');

        $this->countTonight();
        $this->countTonight();

        self::assertSame([['site', '', 2, 2]], $this->siteTotals('2026-11-06'));
    }

    public function testItCountsNothingWhenNoDayIsLeft(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-07 12:00:00');

        $this->countTonight();

        self::assertSame(0, $this->rows('web_visit_total'));
    }

    private function publish(string ...$ids): void
    {
        foreach ($ids as $id) {
            self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: $id, slug: 'p'.substr($id, -3))));
        }
    }

    private function visit(string $page, string $address, string $userAgent = self::BROWSER, string $referrer = '', string $zone = ''): void
    {
        try {
            new VisitRecorder(VisitId::random()->value(), $page, $referrer, '', '', $zone, $address, $userAgent, [self::SITE])(
                self::getContainer()->get(VisitRepository::class),
                self::getContainer()->get(PublishedPages::class),
                self::getContainer()->get(DailySalt::class),
            );
        } catch (DomainError $error) {
            self::fail($error->getMessage());
        }
    }

    private function countTonight(): void
    {
        self::getContainer()->get(NightlyVisitCount::class)();
    }

    /**
     * @return list<array{string, string, int, int}>
     */
    private function totals(string $day): array
    {
        /** @var list<array{dimension: string, value: string, views: int|string, visitors: int|string}> $rows */
        $rows = $this->connection()->fetchAllAssociative('SELECT dimension, value, views, visitors FROM web_visit_total WHERE day = ? ORDER BY dimension, BINARY value', [$day]);

        return array_map(static fn (array $row): array => [$row['dimension'], $row['value'], (int) $row['views'], (int) $row['visitors']], $rows);
    }

    /**
     * @return list<array{string, string, int, int}>
     */
    private function siteTotals(string $day): array
    {
        return array_values(array_filter($this->totals($day), static fn (array $total): bool => 'site' === $total[0]));
    }

    private function visitsLeft(): int
    {
        return $this->rows('web_visit');
    }

    private function rows(string $table): int
    {
        /** @var int|string $rows */
        $rows = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);

        return (int) $rows;
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
