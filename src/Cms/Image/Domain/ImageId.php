<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an image.
 */
final readonly class ImageId extends Uuid {}
