<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\Event\EventBus;

/**
 * Takes a published external resource off the site.
 */
final readonly class ExternalResourceUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $resource->unpublish();
        $resource->save($resourceRepository, $eventBus, $imageStorage);
    }
}
