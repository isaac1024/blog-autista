<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * What a widget shows.
 */
enum WidgetKind: string
{
    /** A page of the blog, as a card of related content. */
    case RelatedPage = 'related_page';
}
