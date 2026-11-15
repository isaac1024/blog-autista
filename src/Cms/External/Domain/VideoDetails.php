<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * A video tells nothing besides its title and its image.
 */
final readonly class VideoDetails extends ResourceDetails
{
    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Video;
    }

    #[\Override]
    public function assertPublishable(): void {}

    #[\Override]
    public function toArray(): array
    {
        return [];
    }
}
