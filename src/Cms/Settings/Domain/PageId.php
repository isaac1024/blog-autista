<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a page, as the menus know it: they only keep which one, never
 * what it is.
 */
final readonly class PageId extends Uuid {}
