<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\HomeSlug;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\Slug;

/**
 * Builds Page aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     PageMother::create()
 *     PageMother::create(title: 'About', slug: 'about')
 *     PageMother::create(title: 'Contacto', slug: 'contacto', type: PageType::Contact)
 *     PageMother::home()
 */
final class PageMother
{
    public static function create(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = TitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
        PageType $type = PageType::Standard,
    ): Page {
        return new Page(PageIdMother::create($id), $type, TitleMother::create($title), SlugMother::create($slug));
    }

    /**
     * The home page, which has no slug.
     */
    public static function home(string $id = PageIdMother::DEFAULT_VALUE, string $title = 'Portada'): Page
    {
        return new Page(PageIdMother::create($id), PageType::Home, TitleMother::create($title), new HomeSlug());
    }

    /**
     * One page of every unique type but the home page, keyed by type, each
     * with its own slug.
     *
     * @return array<string, Page>
     */
    public static function uniqueButHome(string $id = PageIdMother::DEFAULT_VALUE): array
    {
        $pages = [];

        foreach (PageType::uniqueTypes() as $type) {
            if (!$type->isHome()) {
                $slug = str_replace('_', '-', $type->value);
                $pages[$type->value] = new Page(PageIdMother::create($id), $type, TitleMother::create(ucfirst($slug)), new Slug($slug));
            }
        }

        return $pages;
    }
}
