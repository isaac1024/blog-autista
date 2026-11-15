<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

/**
 * The text of a post, out of the HTML its provider writes it in: its
 * paragraphs and line breaks kept as lines, its links as the text they
 * show, everything else as plain text.
 */
final readonly class PostText
{
    public static function fromHtml(string $html): ?string
    {
        $html = (string) preg_replace(['#<br\s*/?>#i', '#</p>\s*<p[^>]*>#i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/[^\S\n]+/u', ' ', $line)), explode("\n", $text));
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));

        return '' === $text ? null : $text;
    }

    /**
     * The text of the first paragraph of a quote an oEmbed answer writes,
     * and its language, as the paragraph tells it.
     *
     * @return array{?string, ?string}
     */
    public static function quoted(string $html): array
    {
        if (1 !== preg_match('#<p(?:\s[^>]*)?>(.*?)</p>#is', $html, $matches)) {
            return [null, null];
        }

        $language = 1 === preg_match('#<p[^>]*\slang="([a-z]{2})#i', $matches[0], $lang) ? strtolower($lang[1]) : null;

        return [self::fromHtml($matches[1]), $language];
    }
}
