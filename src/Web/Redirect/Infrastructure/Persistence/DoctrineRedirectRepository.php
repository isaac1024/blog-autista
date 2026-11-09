<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Redirect\Domain\Redirect;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Outbound adapter that reads the redirects from the table the CMS keeps
 * them in, only reading: the site answers each as soon as the CMS saves it,
 * and no copy of them can lag behind.
 */
#[AsAlias(RedirectRepository::class)]
final readonly class DoctrineRedirectRepository implements RedirectRepository
{
    public function __construct(private Connection $connection) {}

    #[\Override]
    public function findBySource(string $source): ?Redirect
    {
        /** @var array{target_type: ?string, target_id: ?string, origin_id: ?string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT target_type, target_id, origin_id FROM cms_redirect WHERE source = :source',
            ['source' => $source],
        );

        return false === $row ? null : self::redirect($row);
    }

    #[\Override]
    public function findByOrigins(string $type, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<array{target_type: ?string, target_id: ?string, origin_id: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT target_type, target_id, origin_id FROM cms_redirect WHERE origin_type = :type AND origin_id IN (:ids) ORDER BY updated_at DESC, id DESC',
            ['type' => $type, 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_map(self::redirect(...), $rows);
    }

    /**
     * @param array{target_type: ?string, target_id: ?string, origin_id: ?string} $row
     */
    private static function redirect(array $row): Redirect
    {
        return new Redirect($row['target_type'], $row['target_id'], $row['origin_id']);
    }
}
