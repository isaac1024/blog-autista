<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Persistence;

use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\VisitCounts;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that reads the counts the site keeps in its tables: those
 * of the days it already counted, and, for the days it still keeps the
 * visits of, today or one the nightly count missed, the same counts made
 * as it reads them.
 */
#[AsAlias(VisitCounts::class)]
final readonly class DoctrineVisitCounts implements VisitCounts
{
    public function __construct(private Connection $connection) {}

    #[\Override]
    public function during(Days $days): array
    {
        /** @var list<array{day: string, dimension: string, value: string, views: int|string, visitors: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT day, dimension, value, views, visitors FROM web_visit_total WHERE day BETWEEN :first AND :last
            UNION ALL
            SELECT day, 'site', '', COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last GROUP BY day
            UNION ALL
            SELECT day, content_type, content_id, COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last GROUP BY day, content_type, content_id
            UNION ALL
            SELECT day, 'channel', channel, COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last GROUP BY day, channel
            UNION ALL
            SELECT day, 'source', source, COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last AND source IS NOT NULL AND channel <> 'internal' GROUP BY day, source
            UNION ALL
            SELECT day, 'device', device, COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last GROUP BY day, device
            UNION ALL
            SELECT day, 'country', COALESCE(country, ''), COUNT(*), COUNT(DISTINCT reader) FROM web_visit WHERE day BETWEEN :first AND :last GROUP BY day, COALESCE(country, '')
            SQL, ['first' => $days->first(), 'last' => $days->last()]);

        $counts = [];

        foreach ($rows as $row) {
            $dimension = Dimension::tryFrom($row['dimension']);

            if (null !== $dimension) {
                $counts[] = new Count($row['day'], $dimension, $row['value'], (int) $row['views'], (int) $row['visitors']);
            }
        }

        return $counts;
    }

    #[\Override]
    public function oldestUncountedDay(): ?string
    {
        $day = $this->connection->fetchOne('SELECT MIN(day) FROM web_visit');

        return \is_string($day) ? $day : null;
    }
}
