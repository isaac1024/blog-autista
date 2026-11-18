<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ExternalResourceUsage;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Deletes an existing external resource, failing when it doesn't exist.
 */
final readonly class ExternalResourceDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, Transaction $transaction, ResourceImageStorage $imageStorage, ExternalResourceUsage $usage): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $resource->delete($resourceRepository, $eventBus, $transaction, $imageStorage, $usage);
    }
}
