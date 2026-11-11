<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;

/**
 * Checks that tables the migrations built are as the entities map them.
 *
 * MappingTest checks the whole schema, but Infection only runs the test
 * files that cover a mutated line, and reading the mapping runs no line of
 * an entity: the test of the repository that stores it, which builds the
 * entity, checks its tables too, so a mutated column fails where it is
 * covered.
 */
trait MappedTables
{
    private static function assertTablesAreMapped(string ...$tables): void
    {
        $differences = array_values(array_filter(
            new SchemaValidator(self::getContainer()->get(EntityManagerInterface::class))->getUpdateSchemaList(),
            static fn (string $sql): bool => [] !== array_filter($tables, static fn (string $table): bool => 1 === preg_match('/\b'.preg_quote($table, '/').'\b/', $sql)),
        ));

        self::assertSame([], $differences);
    }
}
