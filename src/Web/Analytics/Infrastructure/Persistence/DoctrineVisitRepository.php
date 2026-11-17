<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Analytics\Domain\Channel;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\Dimension;
use Web\Analytics\Domain\Visit;
use Web\Analytics\Domain\VisitRepository;

/**
 * Outbound adapter that keeps the visits through Doctrine ORM, and has the
 * database count them, so no visit is read into PHP to be counted.
 */
#[AsAlias(VisitRepository::class)]
final readonly class DoctrineVisitRepository implements VisitRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Visit $visit): void
    {
        $this->entityManager->persist($visit);
        $this->entityManager->flush();
    }

    #[\Override]
    public function daysToCountBefore(Day $day): array
    {
        /** @var list<string> $days */
        $days = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT day FROM shared_visit WHERE day < :day ORDER BY day',
            ['day' => $day->value()],
        );

        return array_map(static fn (string $value): Day => Day::fromDate(new \DateTimeImmutable($value)), $days);
    }

    #[\Override]
    public function count(Day $day): void
    {
        $connection = $this->entityManager->getConnection();
        $parameters = [
            'day' => $day->value(),
            'site' => Dimension::Site->value,
            'channel' => Dimension::Channel->value,
            'source' => Dimension::Source->value,
            'device' => Dimension::Device->value,
            'country' => Dimension::Country->value,
            'internal' => Channel::Internal->value,
        ];

        // Readers are counted apart in every dimension, so each count of them
        // is exact. Each kind of content is counted by the dimension of its
        // name, such as the posts by "post".
        $connection->executeStatement(<<<'SQL'
            INSERT INTO shared_visit_total (day, dimension, value, views, visitors)
            SELECT day, :site, '', COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day GROUP BY day
            UNION ALL
            SELECT day, content_type, content_id, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day GROUP BY day, content_type, content_id
            UNION ALL
            SELECT day, :channel, channel, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day GROUP BY day, channel
            UNION ALL
            SELECT day, :source, source, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day AND source IS NOT NULL AND channel <> :internal GROUP BY day, source
            UNION ALL
            SELECT day, :device, device, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day GROUP BY day, device
            UNION ALL
            SELECT day, :country, COALESCE(country, ''), COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day = :day GROUP BY day, COALESCE(country, '')
            SQL, $parameters);

        $connection->executeStatement('DELETE FROM shared_visit WHERE day = :day', ['day' => $day->value()]);
    }
}
