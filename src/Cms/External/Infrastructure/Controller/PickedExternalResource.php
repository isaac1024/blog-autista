<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;

/**
 * An external resource as a field that picks one shows it: its title, what
 * it is and where it stands for the site, which only paints it while it is
 * published and available.
 */
final readonly class PickedExternalResource
{
    public function __construct(private ExternalResourceState $resourceState) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(ExternalResourceResponse $resource): array
    {
        return [
            'id' => $resource->id,
            'title' => \sprintf('%s (%s)', $resource->title, $this->resourceState->kind($resource)),
            'status' => $this->resourceState->label($resource),
            'published' => $resource->isPublished() && $resource->available,
        ];
    }
}
