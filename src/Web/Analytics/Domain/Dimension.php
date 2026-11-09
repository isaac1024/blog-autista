<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * What the visits of a day are counted by. The site counts them all at once,
 * under no value; a source counts only the readers who entered through it.
 */
enum Dimension: string
{
    case Site = 'site';
    case Page = 'page';
    case Channel = 'channel';
    case Source = 'source';
    case Device = 'device';
    case Country = 'country';
}
