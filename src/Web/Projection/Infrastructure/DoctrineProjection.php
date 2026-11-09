<?php

declare(strict_types=1);

namespace Web\Projection\Infrastructure;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\RevisedDomainEvent;
use Web\Projection\Domain\Projection;
use Web\Projection\Domain\ProjectionCheckpoint;

/**
 * Applies the projection change and records its revision in one transaction.
 */
final readonly class DoctrineProjection implements Projection
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function apply(string $name, RevisedDomainEvent $event, \Closure $apply): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $checkpoint = $this->entityManager->find(
                ProjectionCheckpoint::class,
                ['projection' => $name, 'aggregateId' => $event->aggregateId()],
                LockMode::PESSIMISTIC_WRITE,
            );

            if (null !== $checkpoint && $event->revision() <= $checkpoint->revision()) {
                $connection->commit();

                return;
            }

            $apply();

            if (null === $checkpoint) {
                $checkpoint = ProjectionCheckpoint::for($name, $event->aggregateId(), $event->revision());
                $this->entityManager->persist($checkpoint);
            } else {
                $checkpoint->advance($event->revision());
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
