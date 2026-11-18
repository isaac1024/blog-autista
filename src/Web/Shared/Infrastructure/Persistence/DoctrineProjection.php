<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Infrastructure\Persistence\Doctrine\TransactionRollback;
use Web\Shared\Domain\Projection;
use Web\Shared\Domain\ProjectionCheckpoint;

/**
 * Applies the projection change and records its revision in one transaction,
 * which consumers of the queue run side by side.
 */
final readonly class DoctrineProjection implements Projection
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function apply(string $name, RevisedReceivedEvent $event, \Closure $apply): void
    {
        $connection = $this->entityManager->getConnection();

        // Read committed, this transaction alone: consumers of the queue
        // apply events at once, and each reads what the others committed,
        // locking only the rows it changes. Repeatable read would keep a
        // snapshot from its first read, and MariaDB refuses to lock or write
        // a row another consumer changed since, such as the count of a file
        // of the sitemap every post of a year adds to. Asked of the server,
        // which may hold a transaction the connection didn't begin, as the
        // tests do; one already begun keeps its own.
        if (\in_array($connection->fetchOne('SELECT @@in_transaction'), [0, '0'], true)) {
            $connection->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        $connection->beginTransaction();

        try {
            // The checkpoint is there before it is locked, at revision 0 the
            // first time: locking a row that isn't there would lock the gap
            // it would go in, where the checkpoints of other aggregates
            // consumed at once are inserted, and they would deadlock.
            $connection->executeStatement(
                'INSERT INTO web_projection_checkpoint (projection, aggregate_id, revision) VALUES (:projection, :aggregate, 0) ON DUPLICATE KEY UPDATE revision = revision',
                ['projection' => $name, 'aggregate' => $event->aggregateId()],
            );
            $checkpoint = $this->entityManager->find(
                ProjectionCheckpoint::class,
                ['projection' => $name, 'aggregateId' => $event->aggregateId()],
                LockMode::PESSIMISTIC_WRITE,
            ) ?? throw new \LogicException('The checkpoint was just made.');

            if ($event->revision() <= $checkpoint->revision()) {
                $connection->commit();

                return;
            }

            $apply();
            $checkpoint->advance($event->revision());
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            TransactionRollback::rethrow($connection, $exception);
        }
    }
}
