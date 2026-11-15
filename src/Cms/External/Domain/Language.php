<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * The language an external resource is written or spoken in, which the site
 * tells readers and their tools, such as screen readers.
 */
enum Language: string
{
    case Spanish = 'es';
    case English = 'en';
    case Catalan = 'ca';
    case Galician = 'gl';
    case Basque = 'eu';
    case French = 'fr';
    case Portuguese = 'pt';
    case Italian = 'it';
    case German = 'de';
}
