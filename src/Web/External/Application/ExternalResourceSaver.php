<?php

declare(strict_types=1);

namespace Web\External\Application;

use Web\External\Domain\CmsExternalResourcePublished;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceId;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;

/**
 * Puts on the site the version of an external resource the CMS published,
 * in place of the one there, if any.
 */
final readonly class ExternalResourceSaver
{
    public function __construct(private CmsExternalResourcePublished $event) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, ImageCopies $imageCopies): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->event->aggregateId()));

        if (null === $resource) {
            $resource = ExternalResource::publish($this->event);
        } else {
            $resource->republish($this->event);
        }

        $resource->save($resourceRepository, $imageCopies);
    }
}
