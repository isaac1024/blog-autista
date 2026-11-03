<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Address of a page within the public site: a slug, or none for the home page.
 */
interface PageSlug
{
    public function value(): string;
}
