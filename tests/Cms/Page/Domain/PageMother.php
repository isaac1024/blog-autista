<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\HomeSlug;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageSlug;
use Cms\Page\Domain\PageStatus;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\PageVersion;
use Cms\Page\Domain\Robots;
use Cms\Page\Domain\Seo;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Timeline;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;

/**
 * Builds Page aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     PageMother::create()
 *     PageMother::create(title: 'About', slug: 'about')
 *     PageMother::create(title: 'Contacto', slug: 'contacto', type: PageType::Contact)
 *     PageMother::create(content: RichTextMother::stored(...), metaDescription: 'Resumen')
 *     PageMother::home()
 *
 * Without a robots directive, a page gets the one it starts with. Pages are
 * drafts created and last changed at ClockMother::NOW.
 */
final class PageMother
{
    /**
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public static function create(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = TitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
        PageType $type = PageType::Standard,
        array $content = RichTextMother::EMPTY,
        ?string $metaDescription = null,
        ?Robots $robots = null,
        string $updatedAt = ClockMother::NOW,
    ): Page {
        return self::draft($id, $type, SlugMother::create($slug), $title, $content, $metaDescription, $robots, $updatedAt);
    }

    /**
     * The home page, which has no slug.
     *
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public static function home(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = 'Portada',
        array $content = RichTextMother::EMPTY,
        ?string $metaDescription = null,
        ?Robots $robots = null,
    ): Page {
        return self::draft($id, PageType::Home, new HomeSlug(), $title, $content, $metaDescription, $robots, ClockMother::NOW);
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
                $pages[$type->value] = self::draft($id, $type, new Slug($slug), ucfirst($slug), RichTextMother::EMPTY, null, null, ClockMother::NOW);
            }
        }

        return $pages;
    }

    /**
     * @param array<array-key, mixed> $content
     */
    private static function draft(
        string $id,
        PageType $type,
        PageSlug $slug,
        string $title,
        array $content,
        ?string $metaDescription,
        ?Robots $robots,
        string $updatedAt,
    ): Page {
        return new Page(
            PageIdMother::create($id),
            $type,
            new PageVersion(
                TitleMother::create($title),
                $slug,
                RichText::fromArray($content),
                new Seo($metaDescription, $robots ?? Seo::initialFor($type)->robots()),
            ),
            PageStatus::Draft,
            null,
            new Timeline(ClockMother::now(), ClockMother::now($updatedAt), null, null),
        );
    }
}
