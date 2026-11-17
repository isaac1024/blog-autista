<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;

/**
 * Takes back a transaction that failed and throws what made it fail.
 *
 * When the server has rolled back the whole transaction on its own, as it
 * does on a deadlock, a nested one has no savepoint left to go back to:
 * rolling it back fails, and the connection would believe it is still within
 * a transaction it no longer has, failing everything after it, such as the
 * worker sending the message back to be retried. The connection is then
 * closed, so it starts again from none, and the failure thrown is the one
 * that rolled it back, not the missing savepoint.
 */
final class TransactionRollback
{
    public static function rethrow(Connection $connection, \Throwable $failure): never
    {
        try {
            $connection->rollBack();
        } catch (\Throwable) {
            $connection->close();
        }

        throw self::cause($failure);
    }

    /**
     * The failure itself, rather than the missing savepoint a nested
     * rollback within it threw in its place: the first the database raised,
     * which the later ones keep as their previous, such as a deadlock or a
     * row changed since it was read.
     */
    private static function cause(\Throwable $failure): \Throwable
    {
        $cause = $failure;

        for ($previous = $failure; null !== $previous; $previous = $previous->getPrevious()) {
            if ($previous instanceof DriverException) {
                $cause = $previous;
            }
        }

        return $cause;
    }
}
