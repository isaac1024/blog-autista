<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\Event\EventBus;

/**
 * Puts the version last saved of an external resource on the site, for the
 * first time or in place of the version there.
 */
final readonly class ExternalResourcePublisher
{
    public function __construct(private string $id) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $resource->publish();
        $resource->save($resourceRepository, $eventBus, $imageStorage);
    }
}
