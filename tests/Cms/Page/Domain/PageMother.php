<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageStatus;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\PageVersion;
use Cms\Shared\Domain\PublicationTimeline;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Shared\Domain\SlugMother;
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
 *     PageMother::published(title: 'Sobre mí', slug: 'sobre-mi')
 *     PageMother::published(title: 'Quién soy', publishedTitle: 'Sobre mí')
 *     PageMother::unpublished()
 *
 * Without a robots directive, a page gets the one it starts with. Pages are
 * created at ClockMother::NOW, and changed and published then unless a test
 * says otherwise. The home page, and any page given a blank slug, has none.
 */
final class PageMother
{
    /**
     * Content enough to publish a page.
     */
    public const array CONTENT = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo en primera persona.']]]],
    ];

    /**
     * A meta description enough to publish a page.
     */
    public const string META_DESCRIPTION = 'Quién escribe este blog sobre autismo y por qué.';

    /**
     * A draft.
     *
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public static function create(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = PageTitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
        PageType $type = PageType::Standard,
        array $content = RichTextMother::EMPTY,
        ?string $metaDescription = null,
        ?Robots $robots = null,
        string $updatedAt = ClockMother::NOW,
    ): Page {
        return new Page(
            PageIdMother::create($id),
            $type,
            self::version($type, $title, $slug, $content, $metaDescription, $robots),
            PageStatus::Draft,
            null,
            new PublicationTimeline(ClockMother::now(), ClockMother::now($updatedAt), null, null),
            0,
        );
    }

    /**
     * The home page, as a draft.
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
        return self::create($id, $title, '', PageType::Home, $content, $metaDescription, $robots);
    }

    /**
     * One draft of every unique type but the home page, keyed by type, each
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
                $pages[$type->value] = self::create($id, ucfirst($slug), $slug, $type);
            }
        }

        return $pages;
    }

    /**
     * A page on the site. With another published title, content or meta
     * description, it is published with changes: the site has the page with
     * those, and the rest as edited.
     *
     * @param array<array-key, mixed>      $content          in the stored rich text format
     * @param null|array<array-key, mixed> $publishedContent in the stored rich text format
     */
    public static function published(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = PageTitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
        PageType $type = PageType::Standard,
        array $content = self::CONTENT,
        ?string $metaDescription = self::META_DESCRIPTION,
        ?string $publishedTitle = null,
        string $updatedAt = ClockMother::NOW,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        ?array $publishedContent = null,
        ?string $publishedMetaDescription = null,
        int $publicationRevision = 1,
    ): Page {
        return new Page(
            PageIdMother::create($id),
            $type,
            self::version($type, $title, $slug, $content, $metaDescription, null),
            PageStatus::Published,
            self::version($type, $publishedTitle ?? $title, $slug, $publishedContent ?? $content, $publishedMetaDescription ?? $metaDescription, null),
            new PublicationTimeline(
                ClockMother::now(),
                ClockMother::now($updatedAt),
                ClockMother::now($firstPublishedAt),
                ClockMother::now($lastPublishedAt),
            ),
            $publicationRevision,
        );
    }

    /**
     * A generic page taken off the site, which keeps the version it had there;
     * with another published title, it was edited since.
     *
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public static function unpublished(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = PageTitleMother::DEFAULT_VALUE,
        string $slug = SlugMother::DEFAULT_VALUE,
        array $content = self::CONTENT,
        string $metaDescription = self::META_DESCRIPTION,
        ?string $publishedTitle = null,
        int $publicationRevision = 2,
    ): Page {
        return new Page(
            PageIdMother::create($id),
            PageType::Standard,
            self::version(PageType::Standard, $title, $slug, $content, $metaDescription, null),
            PageStatus::Unpublished,
            self::version(PageType::Standard, $publishedTitle ?? $title, $slug, $content, $metaDescription, null),
            new PublicationTimeline(ClockMother::now(), ClockMother::now(), ClockMother::now(), ClockMother::now()),
            $publicationRevision,
        );
    }

    /**
     * @param array<array-key, mixed> $content
     */
    private static function version(
        PageType $type,
        string $title,
        string $slug,
        array $content,
        ?string $metaDescription,
        ?Robots $robots,
    ): PageVersion {
        return new PageVersion(
            PageTitleMother::create($title),
            self::slug($type, $slug),
            RichText::fromArray($content),
            new Seo($metaDescription, $robots ?? $type->initialRobots()),
        );
    }

    private static function slug(PageType $type, string $slug): ?Slug
    {
        return $type->isHome() || '' === $slug ? null : new Slug($slug);
    }
}
