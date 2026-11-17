<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\LastAddressPanel;
use Cms\Shared\Infrastructure\Publication\ReplacedRedirectNotice;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names the resources of redirects after what a test keeps,
 * as the CMS would find them by their ids, and tells the editor of a
 * resource on the site, which has no last address to show.
 *
 * @phpstan-require-extends TestCase
 */
trait NamesResources
{
    /**
     * @param list<Page>   $pages
     * @param list<Image>  $images
     * @param list<Post>   $posts
     * @param list<Term>   $terms
     * @param list<Author> $authors
     */
    private function redirectNames(TranslatorInterface $translator, array $pages = [], array $images = [], array $posts = [], array $terms = [], array $authors = []): RedirectNames
    {
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturn($pages);
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturn($images);
        $postRepository = self::createStub(PostRepository::class);
        $postRepository->method('find')->willReturnCallback(static fn (PostId $id): ?Post => array_find($posts, static fn (Post $post): bool => $post->id()->equals($id)));
        $termRepository = self::createStub(TermRepository::class);
        $termRepository->method('find')->willReturnCallback(static fn (TermId $id): ?Term => array_find($terms, static fn (Term $term): bool => $term->id()->equals($id)));
        $authorRepository = self::createStub(AuthorRepository::class);
        $authorRepository->method('findByIds')->willReturn($authors);

        return new RedirectNames($pageRepository, $imageRepository, $postRepository, $termRepository, $authorRepository, $translator);
    }

    /**
     * What the editor of a resource on the site shows of its last address:
     * nothing, since it has its address there.
     */
    private function lastAddressPanelOnSite(TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator): LastAddressPanel
    {
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('isOnSite')->willReturn(true);
        $redirectNames = $this->redirectNames($translator);

        return new LastAddressPanel(
            self::createStub(RedirectRepository::class),
            $siteResources,
            $redirectNames,
            $translator,
            $urlGenerator,
            new ReplacedRedirectNotice($redirectNames, $translator),
        );
    }
}
