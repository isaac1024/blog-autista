<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the entities say of their tables is what the migrations build: a
 * column mapped one way and migrated another would only fail on the data
 * that tells them apart.
 */
final class MappingTest extends KernelTestCase
{
    public function testTheMappingIsValid(): void
    {
        self::assertSame([], new SchemaValidator($this->entityManager())->validateMapping());
    }

    /**
     * The table the migrations keep track of themselves in is no entity's.
     */
    public function testTheMigratedSchemaIsTheMappedOne(): void
    {
        $differences = array_values(array_filter(
            new SchemaValidator($this->entityManager())->getUpdateSchemaList(),
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        ));

        self::assertSame([], $differences);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
