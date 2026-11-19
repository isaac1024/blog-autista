<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Shared\Domain\Projection;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

/**
 * An event is applied only when it advances the projection of its
 * aggregate, and the revision applied is kept with what it changed, or
 * neither is.
 */
final class DoctrineProjectionTest extends KernelTestCase
{
    use MappedTables;

    private const string NAME = 'web_page';

    public function testItAppliesTheFirstEventOfAnAggregateAndKeepsItsRevision(): void
    {
        $applied = $this->apply(2);

        self::assertSame([true, 2], [$applied, $this->revision()]);
    }

    public function testItAppliesOnlyAnEventThatAdvancesTheProjection(): void
    {
        $this->apply(2);

        self::assertSame([false, false, true, 3], [$this->apply(2), $this->apply(1), $this->apply(3), $this->revision()]);
    }

    /**
     * An event already applied ends its transaction as one applied does:
     * the next event of the queue starts from none of its own.
     */
    public function testItLeavesNoTransactionOpenForAnEventAlreadyApplied(): void
    {
        $this->apply(2);
        $connection = self::getContainer()->get(Connection::class);
        $depth = $connection->getTransactionNestingLevel();

        $this->apply(2);

        self::assertSame($depth, $connection->getTransactionNestingLevel());
    }

    /**
     * Each projection keeps its own revision of an aggregate.
     */
    public function testItKeepsTheRevisionOfEachProjectionApart(): void
    {
        $this->apply(2);

        self::assertTrue($this->apply(1, 'web_other'));
    }

    public function testItKeepsNoRevisionWhenTheChangeFails(): void
    {
        $failure = new \RuntimeException('The change failed.');
        $thrown = null;

        try {
            self::getContainer()->get(Projection::class)->apply(self::NAME, CmsPagePublishedMother::create(revision: 2), static fn () => throw $failure);
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertSame([$failure, null], [$thrown, $this->revision()]);
    }

    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_projection_checkpoint');
    }

    /**
     * Whether the change of the event was applied.
     */
    private function apply(int $revision, string $name = self::NAME): bool
    {
        $applied = false;

        self::getContainer()->get(Projection::class)->apply($name, CmsPagePublishedMother::create(revision: $revision), static function () use (&$applied): void {
            $applied = true;
        });

        return $applied;
    }

    private function revision(): ?int
    {
        $revision = self::getContainer()->get(Connection::class)->fetchOne('SELECT revision FROM web_projection_checkpoint WHERE projection = ?', [self::NAME]);

        return \is_numeric($revision) ? (int) $revision : null;
    }
}
