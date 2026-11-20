<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Cms\Shared\Domain\Slug;
use Shared\Domain\SiteTimeZone;

/**
 * What tells a post on the site apart: the day it was first published and
 * its own slug. The day is Madrid's, whatever time zone the server keeps,
 * and never moves; the slug may, each time the post is published again. The
 * section in front of them in its address only says where it is filed.
 */
final readonly class PostAddress
{
    private string $day;

    public function __construct(
        \DateTimeImmutable $firstPublishedAt,
        private Slug $slug,
    ) {
        $this->day = $firstPublishedAt->setTimezone(SiteTimeZone::zone())->format('Y-m-d');
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
     * A name for it alone, such as "2026-11-14/un-post".
     */
    public function key(): string
    {
        return \sprintf('%s/%s', $this->day, $this->slug->value());
    }
}
