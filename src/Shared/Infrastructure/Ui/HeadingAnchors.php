<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\Heading;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\TextRun;
use Shared\Infrastructure\Twig\Components\PostContents;

/**
 * The anchors of the headings of a rich text, which a link to a part of it,
 * such as an entry of the contents of a post, leads to: each heading by the
 * words of its text, in lowercase ASCII joined by hyphens, so the address
 * reads as the heading does. Two headings of one text never share one, nor
 * does a heading take one the page gives something else: a suffix tells
 * them apart, and a heading with no word is a "part".
 */
final class HeadingAnchors
{
    /**
     * The anchors of the page around a rich text, which no heading takes.
     */
    public const array TAKEN = ['main', PostContents::ANCHOR, 'post-contents-title', 'post-tags-title', 'reading-settings', 'reading-settings-title', 'reading-settings-size'];

    /**
     * How many contents a post needs to list them before its text.
     */
    public const int CONTENTS_FROM = 3;

    private const int MAX_LENGTH = 80;

    /**
     * @return array<int, string> the anchor of each heading, keyed by the position of its block in the text
     */
    public static function byBlock(RichText $content): array
    {
        $taken = self::TAKEN;
        $anchors = [];

        foreach ($content->blocks() as $index => $block) {
            if (!$block instanceof Heading) {
                continue;
            }

            $base = self::words(self::text($block));
            $anchor = $base;

            for ($suffix = 2; \in_array($anchor, $taken, true); ++$suffix) {
                $anchor = $base.'-'.$suffix;
            }

            $taken[] = $anchor;
            $anchors[$index] = $anchor;
        }

        return $anchors;
    }

    /**
     * The parts of a text by its headings of the second level, each by its
     * anchor and its words, in order: what the contents of a post list.
     *
     * @return list<array{anchor: string, text: string}>
     */
    public static function contents(RichText $content): array
    {
        $anchors = self::byBlock($content);
        $contents = [];

        foreach ($content->blocks() as $index => $block) {
            if ($block instanceof Heading && 2 === $block->level()) {
                $contents[] = ['anchor' => $anchors[$index], 'text' => self::text($block)];
            }
        }

        return $contents;
    }

    private static function text(Heading $heading): string
    {
        return implode('', array_map(static fn (TextRun $run): string => $run->text(), $heading->text()->runs()));
    }

    private static function words(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $words = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(false === $ascii ? $text : $ascii));
        // No hyphen at either end, where the words begin and where they are cut.
        $words = trim(substr(ltrim($words, '-'), 0, self::MAX_LENGTH), '-');

        return '' === $words ? 'apartado' : $words;
    }
}
