<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\Page\Page;

/**
 * Paints the page of an author as the site does, in the layout it is given:
 * its name as the main heading, its avatar, and its bio below. The site
 * serves it this way, and the CMS previews it the same.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type LinkedContent from RichTextComponents
 * @phpstan-import-type PostCard from PostCards
 */
final readonly class SiteAuthorPage
{
    public function __construct(private RichTextComponents $richTextComponents) {}

    /**
     * Images embedded in the bio and pages it links to are painted from what
     * the caller knows of them, keyed by id, as SitePage paints a page.
     *
     * @param ?array{src: string, srcset: string, size: int, alt: string}                                                $avatar  null for none, as a draft may have
     * @param array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}> $images  as SiteMedia::image() paints them
     * @param array<string, LinkedContent>                                                                               $pages
     * @param array<string, list<PostCard>>                                                                              $mosaics the cards of each mosaic, keyed by Mosaic::key()
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __invoke(Page $layout, string $name, ?array $avatar, RichText $bio, array $images, array $pages, array $mosaics): Page
    {
        $page = $layout->withMain('Shared:PageTitle', ['title' => $name]);

        return (null === $avatar ? $page : $page->withMain('Shared:Avatar', $avatar))
            ->withMain('Shared:Prose', [], ($this->richTextComponents)($bio, $images, $pages, $mosaics))
            // A bio may embed videos and posts of other sites, as a page does.
            ->withFrames(SitePage::frames($bio, $pages));
    }
}
