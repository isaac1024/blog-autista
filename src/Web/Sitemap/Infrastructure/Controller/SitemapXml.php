<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

/**
 * Writes the XML of the sitemap straight out, as it goes, rather than
 * building it whole.
 */
final class SitemapXml
{
    public const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public const string IMAGE_NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public const string CONTENT_TYPE = 'application/xml; charset=UTF-8';

    public static function open(): \XMLWriter
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        return $xml;
    }

    /**
     * Ends the document and writes out what is left of it.
     */
    public static function close(\XMLWriter $xml): void
    {
        $xml->endDocument();
        echo $xml->flush();
    }
}
