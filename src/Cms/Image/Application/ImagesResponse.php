<?php

declare(strict_types=1);

namespace Cms\Image\Application;

/**
 * One page of images, and whether more follow.
 */
final readonly class ImagesResponse
{
    /**
     * @param list<ImageResponse> $images
     */
    public function __construct(
        public array $images,
        public bool $hasMore,
    ) {}
}
