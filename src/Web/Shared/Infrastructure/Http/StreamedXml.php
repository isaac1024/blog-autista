<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Http;

/**
 * Writes an XML document of the site straight out, as it goes, rather than
 * building it whole, such as the sitemap and the feed.
 */
final class StreamedXml
{
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
