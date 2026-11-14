<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * What the site counts its visits by, as it names it. A source counts only
 * the readers who entered through it; each kind of content, such as the
 * pages or the posts, those of its own, by their ids.
 */
enum Dimension: string
{
    case Site = 'site';
    case Page = 'page';
    case Post = 'post';

    /**
     * The pages of the sections and of the tags.
     */
    case Term = 'term';
    case Author = 'author';
    case Channel = 'channel';
    case Source = 'source';
    case Device = 'device';
    case Country = 'country';

    /**
     * The dimensions that count the visits of a kind of content each, by
     * the ids of what they count, such as the pages or the posts.
     *
     * @return list<self>
     */
    public static function contents(): array
    {
        return [self::Page, self::Post, self::Term, self::Author];
    }
}
