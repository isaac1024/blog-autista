<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Domain;

use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermName;
use Cms\Taxonomy\Domain\TermStatus;
use Cms\Taxonomy\Domain\TermVersion;
use Cms\Taxonomy\Domain\Timeline;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds Term aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     TermMother::create()
 *     TermMother::create(taxonomy: Taxonomy::Tag, name: 'Sensorialidad', slug: 'sensorialidad')
 *     TermMother::published(name: 'Vida diaria', publishedName: 'Día a día')
 *     TermMother::unpublished()
 *
 * Terms are created at ClockMother::NOW, and changed and published then
 * unless a test says otherwise. A term given a blank slug has none.
 */
final class TermMother
{
    public const string NAME = 'Vida diaria';

    public const string SLUG = 'vida-diaria';

    /**
     * A meta description enough to publish a term.
     */
    public const string META_DESCRIPTION = 'Artículos sobre la vida diaria de una persona autista.';

    public static function create(
        string $id = TermIdMother::DEFAULT_VALUE,
        Taxonomy $taxonomy = Taxonomy::Section,
        string $name = self::NAME,
        string $slug = self::SLUG,
        ?string $metaDescription = null,
        Robots $robots = Robots::IndexFollow,
        string $updatedAt = ClockMother::NOW,
    ): Term {
        return new Term(
            TermIdMother::create($id),
            $taxonomy,
            self::version($name, $slug, $metaDescription, $robots),
            TermStatus::Draft,
            null,
            new Timeline(ClockMother::now(), ClockMother::now($updatedAt), null, null),
            0,
        );
    }

    /**
     * A term on the site. With another published name, it is published with
     * changes: the site has the term with that name, and the rest as edited.
     */
    public static function published(
        string $id = TermIdMother::DEFAULT_VALUE,
        Taxonomy $taxonomy = Taxonomy::Section,
        string $name = self::NAME,
        string $slug = self::SLUG,
        ?string $metaDescription = self::META_DESCRIPTION,
        ?string $publishedName = null,
        ?string $publishedSlug = null,
        string $updatedAt = ClockMother::NOW,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        int $publicationRevision = 1,
    ): Term {
        return new Term(
            TermIdMother::create($id),
            $taxonomy,
            self::version($name, $slug, $metaDescription, Robots::IndexFollow),
            TermStatus::Published,
            self::version($publishedName ?? $name, $publishedSlug ?? $slug, $metaDescription, Robots::IndexFollow),
            new Timeline(
                ClockMother::now(),
                ClockMother::now($updatedAt),
                ClockMother::now($firstPublishedAt),
                ClockMother::now($lastPublishedAt),
            ),
            $publicationRevision,
        );
    }

    /**
     * A term taken off the site, which keeps the version it had there.
     */
    public static function unpublished(
        string $id = TermIdMother::DEFAULT_VALUE,
        Taxonomy $taxonomy = Taxonomy::Section,
        string $name = self::NAME,
        string $slug = self::SLUG,
        int $publicationRevision = 2,
    ): Term {
        return new Term(
            TermIdMother::create($id),
            $taxonomy,
            self::version($name, $slug, self::META_DESCRIPTION, Robots::IndexFollow),
            TermStatus::Unpublished,
            self::version($name, $slug, self::META_DESCRIPTION, Robots::IndexFollow),
            new Timeline(ClockMother::now(), ClockMother::now(), ClockMother::now(), ClockMother::now()),
            $publicationRevision,
        );
    }

    private static function version(string $name, string $slug, ?string $metaDescription, Robots $robots): TermVersion
    {
        return new TermVersion(
            new TermName($name),
            '' === $slug ? null : new Slug($slug),
            new Seo($metaDescription, $robots),
        );
    }
}
