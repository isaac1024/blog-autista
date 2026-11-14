<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Resolution;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostOnSiteResponse;
use Cms\Post\Application\PostsOnSiteFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Application\TermOnSiteFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a rich text previewed links to, its widgets and the captions of its
 * images too, as the site would show it once published: the pages, posts,
 * sections, tags and authors on the site, each with its address and name
 * there, keyed by the id linked; one off the site, as the one of its kind
 * its last address now leads to, as SiteLinkedContents does on the site.
 *
 * It reaches every module a link can lead to, so its coupling grows with
 * the kinds of content and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class CmsLinkedContents
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content previewed, linked by no text of its own
     *
     * @return array<string, array{url: string, title: string}>
     */
    public function __invoke(RichText $content, array $images, ?string $painted): array
    {
        return [
            ...$this->resolve(LinkType::Page, $content, $images, $painted, fn (array $ids): array => array_map(fn (PageOnSiteResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
            ], new PagesOnSiteFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $content, $images, $painted, fn (array $ids): array => array_map(function (PostOnSiteResponse $post): array {
                [$year, $month, $day] = explode('-', $post->address->day);

                return [
                    'url' => $this->urlGenerator->generate('web_post', ['section' => $post->address->section, 'year' => $year, 'month' => $month, 'day' => $day, 'slug' => $post->address->slug]),
                    'title' => $post->title,
                ];
            }, new PostsOnSiteFinder($ids)($this->postRepository))),
            ...$this->resolve(LinkType::Term, $content, $images, $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $section = new TermOnSiteFinder(Taxonomy::Section, $id)($this->termRepository);

                if (null !== $section) {
                    return ['url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug]), 'title' => $section->name];
                }

                $tag = new TermOnSiteFinder(Taxonomy::Tag, $id)($this->termRepository);

                return null === $tag ? null : ['url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug]), 'title' => $tag->name];
            })),
            ...$this->resolve(LinkType::Author, $content, $images, $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $author = new AuthorOnSiteFinder($id)($this->authorRepository);

                return null === $author ? null : ['url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]), 'title' => $author->name];
            })),
        ];
    }

    /**
     * @param array<string, array{caption: RichText}>                                  $images
     * @param \Closure(list<string>): array<string, array{url: string, title: string}> $onSite
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function resolve(LinkType $type, RichText $content, array $images, ?string $painted, \Closure $onSite): array
    {
        return new ReferenceResolver($type->value, SitePage::linkedIds($content, $images, $type), $painted)($onSite, $this->redirectsByOrigin);
    }

    /**
     * @param list<string>                                         $ids
     * @param \Closure(string): ?array{url: string, title: string} $find
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function each(array $ids, \Closure $find): array
    {
        $found = [];

        foreach ($ids as $id) {
            $content = $find($id);

            if (null !== $content) {
                $found[$id] = $content;
            }
        }

        return $found;
    }
}
