<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * What a widget shows.
 */
enum WidgetKind: string
{
    /**
     * A content of the blog, as a card of related content. Named after the
     * pages, the first it showed, as the content kept says it.
     */
    case RelatedPage = 'related_page';
}
