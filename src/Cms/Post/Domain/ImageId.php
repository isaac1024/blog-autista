<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an image of the library, as a post knows it: it only keeps
 * which one opens it, never what it is.
 */
final readonly class ImageId extends Uuid {}
