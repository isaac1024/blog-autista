<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Site;

use Shared\Domain\ImageCopy;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Redirect\Domain\TargetAddresses;

/**
 * Outbound adapter that asks the pages and the images of the site where
 * each is served: a page at its slug, or the root for the home page, and an
 * image at the copy of the width.
 */
#[AsAlias(TargetAddresses::class)]
final readonly class SiteTargetAddresses implements TargetAddresses
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public function addressOf(string $type, string $id, ?int $width): ?string
    {
        return match ($type) {
            'page' => $this->pageAddress($id),
            'image' => $this->imageAddress($id, $width ?? ImageCopy::LARGEST),
            default => null,
        };
    }

    private function pageAddress(string $id): ?string
    {
        $page = new PublishedPagesFinder([$id])($this->pageRepository)[$id] ?? null;

        if (null === $page) {
            return null;
        }

        return null === $page->slug
            ? $this->urlGenerator->generate('web_home')
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]);
    }

    private function imageAddress(string $id, int $width): ?string
    {
        $image = new PublishedImagesFinder([$id])($this->imageRepository)[$id] ?? null;

        return null === $image ? null : new ImageCopy($image->path, $width)->url();
    }
}
