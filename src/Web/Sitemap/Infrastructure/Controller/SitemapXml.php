<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

/**
 * What the XML of the sitemap is written in, which StreamedXml writes out.
 */
final class SitemapXml
{
    public const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public const string IMAGE_NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public const string CONTENT_TYPE = 'application/xml; charset=UTF-8';
}
