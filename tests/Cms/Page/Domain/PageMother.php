<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\Page;

/**
 * Builds Page aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     PageMother::create()
 *     PageMother::create(title: 'About', slug: 'about')
 */
final class PageMother
{
    public static function create(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = TitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
    ): Page {
        return new Page(PageIdMother::create($id), TitleMother::create($title), SlugMother::create($slug));
    }
}
