<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\SiteTimeZone;

/**
 * Where the site serves a post: under the slug its section had when the post
 * was last published, on the day it was first published, by its own slug.
 * The day is Madrid's, whatever time zone the server keeps, and never moves;
 * the section and the slug may, each time the post is published again.
 */
final readonly class PostAddress
{
    private string $day;

    public function __construct(
        private Slug $section,
        \DateTimeImmutable $firstPublishedAt,
        private Slug $slug,
    ) {
        $this->day = $firstPublishedAt->setTimezone(SiteTimeZone::zone())->format('Y-m-d');
    }

    public function section(): Slug
    {
        return $this->section;
    }

    /**
     * The day it was first published, as Y-m-d.
     */
    public function day(): string
    {
        return $this->day;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    /**
     * A name for it alone, such as "vida-diaria/2026-11-14/un-post".
     */
    public function key(): string
    {
        return \sprintf('%s/%s/%s', $this->section->value(), $this->day, $this->slug->value());
    }
}
