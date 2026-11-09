<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * What the site counts its visits by, as it names it. A source counts only
 * the readers who entered through it.
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
