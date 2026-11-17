<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * The time zone of the blog, that of its author: the day a post is first
 * published, which its address keeps, the date it shows, and the days its
 * visits are counted by are all days of Madrid.
 */
final class SiteTimeZone
{
    public const string NAME = 'Europe/Madrid';

    public static function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::NAME);
    }
}
