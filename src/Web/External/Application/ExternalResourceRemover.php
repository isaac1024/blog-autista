<?php

declare(strict_types=1);

namespace Web\External\Application;

use Web\External\Domain\CmsExternalResourceUnpublished;
use Web\External\Domain\ExternalResourceId;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;

/**
 * Takes off the site an external resource the CMS unpublished. A resource
 * already gone stays gone, so the same event can be taken twice.
 */
final readonly class ExternalResourceRemover
{
    public function __construct(private CmsExternalResourceUnpublished $event) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, ImageCopies $imageCopies): void
    {
        $resourceRepository->find(new ExternalResourceId($this->event->aggregateId()))?->unpublish($resourceRepository, $imageCopies);
    }
}
