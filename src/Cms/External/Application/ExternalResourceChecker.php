<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\CheckOutcome;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Shared\Domain\Event\EventBus;

/**
 * Checks whether the address of an external resource still leads to it,
 * and keeps what that found.
 */
final readonly class ExternalResourceChecker
{
    public function __construct(private string $id) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceReader $reader, ResourceImageStorage $imageStorage): CheckOutcome
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $outcome = $reader->check($resource->checked()->url(), $resource->kind(), $resource->provider(), $resource->checked()->locator());
        $resource->check($outcome);
        $resource->save($resourceRepository, $eventBus, $imageStorage);

        return $outcome;
    }
}
