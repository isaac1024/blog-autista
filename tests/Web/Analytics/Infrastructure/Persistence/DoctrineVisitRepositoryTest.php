<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Analytics\Domain\Channel;
use Web\Analytics\Domain\ContentType;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\Device;
use Web\Analytics\Domain\Visit;
use Web\Analytics\Domain\VisitRepository;
use Web\Tests\Analytics\Domain\VisitIdMother;
use Web\Tests\Analytics\Domain\VisitMother;

/**
 * The visits are kept until their day is counted, and the database counts
 * them by every dimension: each counts its own readers.
 */
final class DoctrineVisitRepositoryTest extends KernelTestCase
{
    use MappedTables;

    private const string OTHER_PAGE = '01a1b801-ce58-723d-8234-b76d9105e202';
    private const string ONE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string TWO = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string THREE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    /**
     * Every field comes back as saved, its source at its longest.
     */
    public function testItSavesTheVisitSoItCanBeReadBack(): void
    {
        $visit = VisitMother::create(channel: Channel::Referral, source: str_repeat('a', 200), device: Device::Mobile, country: 'MX');

        $this->repository()->save($visit);
        $this->entityManager()->clear();

        self::assertEquals($visit, $this->entityManager()->find(Visit::class, VisitIdMother::create()));
    }

    public function testItTellsTheDaysBeforeOneThatStillHaveVisitsOldestFirst(): void
    {
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), day: '2026-11-06'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), day: '2026-11-05'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), day: '2026-11-05'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), day: '2026-11-07'));

        self::assertSame(['2026-11-05', '2026-11-06'], array_map(
            static fn (Day $day): string => $day->value(),
            $this->repository()->daysToCountBefore(Day::fromDate(new \DateTimeImmutable('2026-11-07'))),
        ));
    }

    /**
     * Two pages seen by one reader are two views of one reader for the site,
     * and one view each for the pages. A source counts only readers who
     * entered through it, never those coming from within the site, and an
     * unknown country counts under no value.
     */
    public function testItCountsTheVisitsOfADayByEveryDimensionAndForgetsThem(): void
    {
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), channel: Channel::Search, source: 'google.es', reader: self::ONE, country: 'ES'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), page: self::OTHER_PAGE, channel: Channel::Internal, source: 'blogautista.localhost', reader: self::ONE, country: 'ES'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), channel: Channel::Search, source: 'google.es', reader: self::TWO, device: Device::Mobile, country: 'MX'));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), reader: self::THREE));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), day: '2026-11-08', reader: self::THREE));

        $this->repository()->count(Day::fromDate(new \DateTimeImmutable(VisitMother::DAY)));

        self::assertSame([
            ['channel', 'direct', 1, 1],
            ['channel', 'internal', 1, 1],
            ['channel', 'search', 2, 2],
            ['country', '', 1, 1],
            ['country', 'ES', 2, 1],
            ['country', 'MX', 1, 1],
            ['device', 'desktop', 3, 2],
            ['device', 'mobile', 1, 1],
            ['page', VisitMother::PAGE, 3, 3],
            ['page', self::OTHER_PAGE, 1, 1],
            ['site', '', 4, 3],
            ['source', 'google.es', 2, 2],
        ], $this->totals(VisitMother::DAY));
        // Only the day counted is forgotten.
        self::assertSame(['2026-11-08'], $this->connection()->fetchFirstColumn('SELECT day FROM web_visit'));
    }

    /**
     * Each kind of content is counted by the dimension of its name, by the
     * ids of what was seen.
     */
    public function testItCountsEachKindOfContentByItsOwnDimension(): void
    {
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), reader: self::ONE, type: ContentType::Post));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), reader: self::TWO, type: ContentType::Post));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), page: self::OTHER_PAGE, reader: self::ONE, type: ContentType::Term));
        $this->save(VisitMother::create(id: VisitIdMother::random()->value(), reader: self::THREE, type: ContentType::Author));

        $this->repository()->count(Day::fromDate(new \DateTimeImmutable(VisitMother::DAY)));

        self::assertSame(
            [['author', VisitMother::PAGE, 1, 1], ['post', VisitMother::PAGE, 2, 2], ['term', self::OTHER_PAGE, 1, 1]],
            array_values(array_filter($this->totals(VisitMother::DAY), static fn (array $total): bool => \in_array($total[0], ['page', 'post', 'term', 'author'], true))),
        );
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTablesAreTheOnesMapped(): void
    {
        self::assertTablesAreMapped('web_visit', 'web_visit_total');
    }

    private function save(Visit $visit): void
    {
        $this->repository()->save($visit);
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

    private function repository(): VisitRepository
    {
        return self::getContainer()->get(VisitRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
