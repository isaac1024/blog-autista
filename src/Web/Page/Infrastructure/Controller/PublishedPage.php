<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\RichTextComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;

/**
 * Composes a page of the site from Twig components: its title as the main
 * heading, its content below, and how it presents itself to search engines,
 * with its own address as its canonical URL, in the chrome of the site.
 */
final readonly class PublishedPage
{
    /**
     * Where the site serves the copies of its images, and their widths, all 16:9.
     */
    private const string MEDIA_URL = '/media/';

    private const array WIDTHS = [400 => 225, 800 => 450, 1200 => 675];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RichTextComponents $richTextComponents,
        private ImageRepository $imageRepository,
        private PageRepository $pageRepository,
        private SiteLayout $siteLayout,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $canonical = null === $page->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        $images = $this->images($page);

        // Search engines may show its images large, in results and Discover.
        return $this->siteLayout->page($page->title)
            ->withSearchEngineMetadata($page->metaDescription, $page->robots.',max-image-preview:large', $canonical)
            ->withMain('Shared:PageTitle', ['title' => $page->title])
            ->withMain('Shared:Prose', [], ($this->richTextComponents)($page->content, $images, $this->links($page, $images)));
    }

    /**
     * The images on the site the page embeds, as the content paints them.
     *
     * @return array<string, array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}>
     */
    private function images(PageResponse $page): array
    {
        return array_map(static function (ImageResponse $image): array {
            $copy = static fn (int $width): string => \sprintf('%s%s-%dw.webp', self::MEDIA_URL, preg_replace('/\.webp$/', '', $image->path), $width);

            return [
                'src' => $copy(1200),
                'srcset' => implode(', ', array_map(static fn (int $width): string => \sprintf('%s %dw', $copy($width), $width), array_keys(self::WIDTHS))),
                'width' => 1200,
                'height' => self::WIDTHS[1200],
                'alt' => $image->alt,
                'caption' => $image->caption,
            ];
        }, new PublishedImagesFinder($page->content->imageIds())($this->imageRepository));
    }

    /**
     * Current addresses of the published pages the content and the captions
     * of its images link to, keyed by id. A target not on the site has no
     * address, so its text stays unlinked.
     *
     * @param array<string, array{caption: RichText}> $images
     *
     * @return array<string, string>
     */
    private function links(PageResponse $page, array $images): array
    {
        $pageIds = $page->content->pageIds();

        foreach ($images as $image) {
            array_push($pageIds, ...$image['caption']->pageIds());
        }

        return array_map(function (PageResponse $target): string {
            return null === $target->slug
                ? $this->urlGenerator->generate('web_home')
                : $this->urlGenerator->generate('web_page', ['slug' => $target->slug]);
        }, new PublishedPagesFinder(array_values(array_unique($pageIds)))($this->pageRepository));
    }
}
