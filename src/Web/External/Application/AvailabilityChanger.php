<?php

declare(strict_types=1);

namespace Web\External\Application;

use Web\External\Domain\CmsExternalResourceAvailabilityChanged;
use Web\External\Domain\ExternalResourceId;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;

/**
 * Paints an external resource on the site again, or stops painting it,
 * as checking its address found. One off the site has nothing to change.
 */
final readonly class AvailabilityChanger
{
    public function __construct(private CmsExternalResourceAvailabilityChanged $event) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, ImageCopies $imageCopies): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->event->aggregateId()));

        if (null === $resource) {
            return;
        }

        $resource->changeAvailability($this->event->available());
        $resource->save($resourceRepository, $imageCopies);
    }
}
