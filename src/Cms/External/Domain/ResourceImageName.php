<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Uuid;

/**
 * The name of the file of an image of an external resource, unique, so no
 * two images ever share a file.
 */
final readonly class ResourceImageName extends Uuid {}
