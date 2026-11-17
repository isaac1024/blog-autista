<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Persistence\Doctrine\TransactionRollback;

final class TransactionRollbackTest extends TestCase
{
    /**
     * A transaction the server still has is rolled back, and what failed in
     * it is thrown as it was.
     */
    public function testItRollsBackAndThrowsTheFailure(): void
    {
        $failure = new \RuntimeException('The change failed.');
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('close');

        self::assertSame($failure, self::thrown($connection, $failure));
    }

    /**
     * When the server rolled back the whole transaction, as on a deadlock or
     * a row changed since it was read, the missing savepoint is no failure
     * of its own: the connection starts again from none, and the failure of
     * the database that made it, which the savepoint keeps as its previous,
     * is thrown.
     */
    public function testItStartsTheConnectionAgainAndThrowsWhatTheServerRolledBackFor(): void
    {
        $changed = new DriverException(self::driverFailure('Record has changed since last read in table \'web_sitemap_file\''), null);
        $savepoint = new DriverException(self::driverFailure('SAVEPOINT DOCTRINE_2 does not exist', $changed), null);
        $connection = $this->createMock(Connection::class);
        $connection->method('rollBack')->willThrowException($savepoint);
        $connection->expects(self::once())->method('close');

        self::assertSame($changed, self::thrown($connection, $savepoint));
    }

    private static function driverFailure(string $message, ?\Throwable $previous = null): Driver\Exception
    {
        return new class($message, 0, $previous) extends \RuntimeException implements Driver\Exception {
            #[\Override]
            public function getSQLState(): ?string
            {
                return null;
            }
        };
    }

    private static function thrown(Connection $connection, \Throwable $failure): \Throwable
    {
        try {
            TransactionRollback::rethrow($connection, $failure);
        } catch (\Throwable $thrown) {
            return $thrown;
        }
    }
}
