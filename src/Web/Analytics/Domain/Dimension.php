<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * What the visits of a day are counted by. The site counts them all at once,
 * under no value; a source counts only the readers who entered through it;
 * each kind of content, such as the pages or the posts, counts those of its
 * own, by their ids.
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
}
