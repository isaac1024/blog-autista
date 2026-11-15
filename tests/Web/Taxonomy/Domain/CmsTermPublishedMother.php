<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Domain;

use Shared\Tests\Domain\ClockMother;
use Web\Shared\Domain\Robots;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Taxonomy;

/**
 * Builds the event the CMS sends the site when it publishes a section or a
 * tag, as the site reads it, or its body as it travels:
 *
 *     CmsTermPublishedMother::create(taxonomy: Taxonomy::Tag)
 *     CmsTermPublishedMother::body(taxonomy: 'tag')
 *
 * It happens at ClockMother::NOW unless a test says otherwise.
 */
final class CmsTermPublishedMother
{
    /**
     * The body of the event as it travels from the CMS.
     *
     * @return array<string, mixed>
     */
    public static function body(string $taxonomy = 'section', string $name = 'Vida diaria', string $slug = 'vida-diaria', string $metaDescription = 'Artículos sobre la vida diaria.'): array
    {
        return [
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'meta_description' => $metaDescription,
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    public static function create(
        string $id = TermIdMother::DEFAULT_VALUE,
        Taxonomy $taxonomy = Taxonomy::Section,
        string $name = 'Vida diaria',
        string $slug = 'vida-diaria',
        ?string $metaDescription = 'Artículos sobre la vida diaria.',
        Robots $robots = Robots::IndexFollow,
        string $lastPublishedAt = ClockMother::NOW,
        int $revision = 1,
    ): CmsTermPublished {
        return new CmsTermPublished(
            $id,
            $taxonomy,
            $name,
            $slug,
            $metaDescription,
            $robots,
            ClockMother::now(),
            ClockMother::now($lastPublishedAt),
            $revision,
        );
    }
}
