<?php

declare(strict_types=1);

namespace Web\Image\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an image on the site: the one it has in the CMS it comes from.
 */
final readonly class ImageId extends Uuid {}
