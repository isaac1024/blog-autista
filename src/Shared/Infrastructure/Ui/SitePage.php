<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\Widget;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\Page\Page;

/**
 * Paints a page as the site does, in the layout it is given, which already
 * tells search engines what the caller wants them told: its title as the main
 * heading, and its content below. The site serves it this way, and the CMS
 * previews it the same.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type PostCard from PostCards
 */
final readonly class SitePage
{
    public function __construct(private RichTextComponents $richTextComponents) {}

    /**
     * Embedded images and linked contents are painted from what the caller
     * knows of them, keyed by id; one it doesn't give, such as one off the
     * site, isn't painted, and text linking it stays plain.
     *
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images  as SiteMedia::image() paints them
     * @param array<string, array{url: string, title: string, external?: ExternalView}>                                  $pages
     * @param array<string, list<PostCard>>                                                                              $mosaics the cards of each mosaic, keyed by Mosaic::key()
     */
    public function __invoke(
        Page $layout,
        string $title,
        RichText $content,
        array $images,
        array $pages,
        array $mosaics,
    ): Page {
        return $layout
            ->withMain('Shared:PageTitle', ['title' => $title])
            ->withMain('Shared:Prose', [], ($this->richTextComponents)($content, $images, $pages, $mosaics))
            ->withFrames(self::frames($content, $pages));
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

    /**
     * The origins of the resources the widgets of the content embed, which
     * the page lets frame them, each once: those of a page, and of a post.
     *
     * @param array<string, array{url: string, title: string, external?: ExternalView}> $pages
     *
     * @return list<string>
     */
    public static function frames(RichText $content, array $pages): array
    {
        $origins = [];

        foreach ($content->blocks() as $block) {
            $external = $block instanceof Widget ? $pages[$block->target()->id()]['external'] ?? null : null;
            $origin = null === $external ? null : ExternalComponents::frameOrigin($external);

            if (null !== $origin) {
                $origins[$origin] = $origin;
            }
        }

        return array_values($origins);
    }
}
