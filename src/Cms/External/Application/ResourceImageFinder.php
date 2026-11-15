<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceImagePath;

/**
 * Finds the file of an image of an external resource, by its name: one of
 * the version being edited or of the one last published, and no other.
 */
final readonly class ResourceImageFinder
{
    public function __construct(
        private string $id,
        private string $image,
    ) {}

    /**
     * @return string where the file is kept, relative to the image storage
     */
    public function __invoke(ExternalResourceRepository $resourceRepository): string
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);
        $image = new ResourceImagePath($this->image);

        if (!$resource->hasImage($image)) {
            throw ExternalResourceNotFound::withId($this->id);
        }

        return $image->value();
    }
}
