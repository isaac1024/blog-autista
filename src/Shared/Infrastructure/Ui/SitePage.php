<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;

/**
 * Paints a page as the site does, in the layout it is given, which already
 * tells search engines what the caller wants them told: its title as the main
 * heading, and its content below. The site serves it this way, and the CMS
 * previews it the same.
 */
final readonly class SitePage
{
    public function __construct(private RichTextComponents $richTextComponents) {}

    /**
     * Embedded images and linked contents are painted from what the caller
     * knows of them, keyed by id; one it doesn't give, such as one off the
     * site, isn't painted, and text linking it stays plain.
     *
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images as SiteMedia::image() paints them
     * @param array<string, array{url: string, title: string}>                                                           $pages
     */
    public function __invoke(
        Page $layout,
        string $title,
        RichText $content,
        array $images,
        array $pages,
    ): Page {
        return $layout
            ->withMain('Shared:PageTitle', ['title' => $title])
            ->withMain('Shared:Prose', [], ($this->richTextComponents)($content, $images, $pages));
    }

    /**
     * What of the kind the content, its widgets and the captions of its
     * images link to, each once.
     *
     * @param array<string, array{caption: RichText}> $images
     *
     * @return list<string>
     */
    public static function linkedIds(RichText $content, array $images, LinkType $type): array
    {
        $ids = $content->linkedIds($type);

        foreach ($images as $image) {
            array_push($ids, ...$image['caption']->linkedIds($type));
        }

        return array_values(array_unique($ids));
    }
}
