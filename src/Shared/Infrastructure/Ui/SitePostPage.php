<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;
use Shared\Domain\SiteTimeZone;
use Shared\Infrastructure\Ui\Page\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes a post as the site serves it, and as the CMS previews it: its
 * section, its title, who signs it, when it was published and how long it
 * takes to read, the image it opens with, its content, and its tags. It is
 * shared as a post, with its opening image.
 *
 * It always dates the post by when it was last published, the day of Madrid
 * the site keeps, and says it was updated when that day isn't the one it
 * was first published.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type LinkedContent from RichTextComponents
 */
final readonly class SitePostPage
{
    public function __construct(
        private RichTextComponents $richTextComponents,
        private TranslatorInterface $translator,
    ) {}

    /**
     * The section and the author lead to their pages while they are on the
     * site, a null url otherwise, and are named as text then; the author shows
     * their avatar while they have one. Embedded images
     * and linked pages are painted from what the caller knows of them, keyed
     * by id, as SitePage paints a page. The opening image is painted the same
     * way, opening the text, and is left out while the caller has none of it
     * on the site.
     *
     * @param array{name: string, url: ?string}                                                                          $section
     * @param array{name: string, url: ?string, avatar: ?array{src: string, srcset: string, size: int, alt: string}}     $author
     * @param list<array{name: string, url: string}>                                                                     $tags    those on the site, in order
     * @param ?array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}               $opening as SiteMedia::image() paints it, null for none
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images  as SiteMedia::image() paints them
     * @param array<string, LinkedContent>                                                                               $pages
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __invoke(
        Page $layout,
        string $title,
        \DateTimeImmutable $firstPublishedAt,
        \DateTimeImmutable $lastPublishedAt,
        int $readingMinutes,
        array $section,
        array $author,
        array $tags,
        ?array $opening,
        RichText $content,
        array $images,
        array $pages,
    ): Page {
        $zone = SiteTimeZone::zone();
        $last = $lastPublishedAt->setTimezone($zone);
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, SiteTimeZone::NAME);

        // A post shows no mosaic of posts.
        $prose = ($this->richTextComponents)($content, $images, $pages, []);

        if (null !== $opening) {
            array_unshift($prose, $this->richTextComponents->opening($opening, $pages));
            $layout = $layout->withShareImage($opening['src'], $opening['width'], $opening['height'], $opening['alt']);
        }

        $page = $layout
            ->withArticle($firstPublishedAt, $lastPublishedAt, '' === $section['name'] ? null : $section['name'], array_column($tags, 'name'))
            ->withMain('Shared:PostHeader', [
                'title' => $title,
                'updated' => $last->format('Y-m-d') !== $firstPublishedAt->setTimezone($zone)->format('Y-m-d'),
                'date' => (string) $formatter->format($last),
                'datetime' => $last->format(\DATE_ATOM),
                'readingMinutes' => $readingMinutes,
                'section' => $section,
                'author' => $author,
            ])
            ->withMain('Shared:Prose', [], $prose)
            ->withFrames(SitePage::frames($content, $pages));

        return [] === $tags ? $page : $page->withMain('Shared:PostTags', ['tags' => $tags]);
    }
}
