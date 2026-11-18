<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Persistence;

use Cms\Analytics\Domain\ContentTotal;
use Cms\Analytics\Domain\Count;
use Cms\Analytics\Domain\Days;
use Cms\Analytics\Domain\Dimension;
use Cms\Analytics\Domain\SeenContents;
use Cms\Analytics\Domain\VisitCounts;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that reads the counts the site keeps in its tables: those
 * of the days it already counted, and, for the days it still keeps the
 * visits of, today or one the nightly count missed, the same counts made
 * as it reads them. The contents are added up and ranked by the database,
 * which hands over only the most seen: a blog is read across thousands of
 * posts.
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
            SELECT day, dimension, value, views, visitors FROM shared_visit_total WHERE day BETWEEN :first AND :last AND dimension NOT IN (:contents)
            UNION ALL
            SELECT day, 'site', '', COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last GROUP BY day
            UNION ALL
            SELECT day, 'channel', channel, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last GROUP BY day, channel
            UNION ALL
            SELECT day, 'source', source, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last AND source IS NOT NULL AND channel <> 'internal' GROUP BY day, source
            UNION ALL
            SELECT day, 'device', device, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last GROUP BY day, device
            UNION ALL
            SELECT day, 'country', COALESCE(country, ''), COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last GROUP BY day, COALESCE(country, '')
            SQL, ['first' => $days->first(), 'last' => $days->last(), 'contents' => self::contents()], ['contents' => ArrayParameterType::STRING]);

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
    public function contentsDuring(Days $days, int $mostSeen): SeenContents
    {
        // The window adds up every content seen before the limit keeps the
        // most seen, so one query tells both.
        /** @var list<array{dimension: string, value: string, views: int|string, visitors: int|string, contents: int|string, all_views: int|string, all_visitors: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT dimension, value, views, visitors,
                COUNT(*) OVER () AS contents, SUM(views) OVER () AS all_views, SUM(visitors) OVER () AS all_visitors
            FROM (
                SELECT dimension, value, SUM(views) AS views, SUM(visitors) AS visitors
                FROM (
                    SELECT dimension, value, views, visitors FROM shared_visit_total WHERE day BETWEEN :first AND :last AND dimension IN (:contents)
                    UNION ALL
                    SELECT content_type, content_id, COUNT(*), COUNT(DISTINCT reader) FROM shared_visit WHERE day BETWEEN :first AND :last AND content_type IN (:contents) GROUP BY day, content_type, content_id
                ) AS counts
                GROUP BY dimension, value
            ) AS seen
            ORDER BY views DESC, dimension, value
            LIMIT :most_seen
            SQL, [
            'first' => $days->first(),
            'last' => $days->last(),
            'contents' => self::contents(),
            'most_seen' => $mostSeen,
        ], ['contents' => ArrayParameterType::STRING, 'most_seen' => ParameterType::INTEGER]);

        return new SeenContents(
            array_map(static fn (array $row): ContentTotal => new ContentTotal(Dimension::from($row['dimension']), $row['value'], (int) $row['views'], (int) $row['visitors']), $rows),
            (int) ($rows[0]['contents'] ?? 0),
            (int) ($rows[0]['all_views'] ?? 0),
            (int) ($rows[0]['all_visitors'] ?? 0),
        );
    }

    #[\Override]
    public function oldestUncountedDay(): ?string
    {
        $day = $this->connection->fetchOne('SELECT MIN(day) FROM shared_visit');

        return \is_string($day) ? $day : null;
    }

    /**
     * @return list<string>
     */
    private static function contents(): array
    {
        return array_map(static fn (Dimension $dimension): string => $dimension->value, Dimension::contents());
    }
}
