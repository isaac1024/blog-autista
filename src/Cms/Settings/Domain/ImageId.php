<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an image of the library, as the settings know it: they only
 * keep which one, never what it is.
 */
final readonly class ImageId extends Uuid {}
