<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Site;

use Shared\Domain\ImageCopy;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PagesOnSiteFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostOnSiteFinder;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Domain\TargetAddresses;
use Web\Shared\Infrastructure\Ui\SitePostUrls;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Outbound adapter that asks the resources of the site where each is
 * served: a page at its slug, or the root for the home page, an image at the
 * copy of the width, a post at its canonical address, a section or a tag
 * at the slug of its taxonomy, and an author at its own.
 *
 * It reaches every module a redirect can lead to, so its coupling and what
 * it takes grow with the kinds of resource and say nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsAlias(TargetAddresses::class)]
final readonly class SiteTargetAddresses implements TargetAddresses
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private UrlGeneratorInterface $urlGenerator,
        private SitePostUrls $sitePostUrls,
    ) {}

    #[\Override]
    public function addressOf(string $type, string $id, ?int $width): ?string
    {
        return match ($type) {
            'page' => $this->pageAddress($id),
            'image' => $this->imageAddress($id, $width ?? ImageCopy::LARGEST),
            'post' => $this->postAddress($id),
            'term' => $this->termAddress($id),
            'author' => $this->authorAddress($id),
            default => null,
        };
    }

    private function pageAddress(string $id): ?string
    {
        $page = new PagesOnSiteFinder([$id])($this->pageRepository)[$id] ?? null;

        if (null === $page) {
            return null;
        }

        return null === $page->slug
            ? $this->urlGenerator->generate('web_home')
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]);
    }

    private function imageAddress(string $id, int $width): ?string
    {
        $image = new ImagesOnSiteFinder([$id])($this->imageRepository)[$id] ?? null;

        return null === $image ? null : new ImageCopy($image->path, $width)->url();
    }

    private function postAddress(string $id): ?string
    {
        $post = new PostOnSiteFinder($id)($this->postRepository);

        if (null === $post) {
            return null;
        }

        return ($this->sitePostUrls)([['sectionId' => $post->sectionId, 'day' => $post->day, 'slug' => $post->slug]], UrlGeneratorInterface::ABSOLUTE_PATH)[0];
    }

    /**
     * A term is a section or a tag, each served under its own route.
     */
    private function termAddress(string $id): ?string
    {
        $section = new TermOnSiteFinder(TermResponse::SECTION, $id)($this->termRepository);

        if (null !== $section) {
            return $this->urlGenerator->generate('web_section', ['slug' => $section->slug]);
        }

        $tag = new TermOnSiteFinder(TermResponse::TAG, $id)($this->termRepository);

        return null === $tag ? null : $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug]);
    }

    private function authorAddress(string $id): ?string
    {
        $author = new AuthorOnSiteFinder($id)($this->authorRepository);

        return null === $author ? null : $this->urlGenerator->generate('web_author', ['slug' => $author->slug]);
    }
}
