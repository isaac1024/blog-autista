<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Shared\Domain\Event\EventBus;

/**
 * Fills the version being edited of an external resource from its address
 * anew: what it tells of itself there now, and its image.
 */
final readonly class ExternalResourceFiller
{
    public function __construct(private string $id) {}

    /**
     * Whether the resource could be read; when it couldn't, nothing changed.
     */
    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceReader $reader, ResourceImageFetcher $imageFetcher, ResourceImageStorage $imageStorage): bool
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $reading = $reader->read($resource->version()->url());
        $resource->fill($reading, $imageFetcher, $imageStorage);
        $resource->save($resourceRepository, $eventBus, $imageStorage);

        return $reading->reached;
    }
}
