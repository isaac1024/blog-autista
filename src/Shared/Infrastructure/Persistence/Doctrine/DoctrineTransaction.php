<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Transaction;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that runs the work in a transaction of the database
 * connection. The events are queued on that same connection, so they are
 * kept, or dropped, with what the work saved.
 */
#[AsAlias(Transaction::class)]
final readonly class DoctrineTransaction implements Transaction
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function run(\Closure $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $work();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            TransactionRollback::rethrow($connection, $exception);
        }
    }
}
