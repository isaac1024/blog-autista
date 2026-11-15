<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Resolution;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\External\Infrastructure\Ui\CmsExternalViews;
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
use Shared\Infrastructure\Ui\ExternalComponents;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a rich text previewed links to, its widgets and the captions of its
 * images too, as the site would show it once published: the pages, posts,
 * sections, tags and authors on the site, each with its address and name
 * there, keyed by the id linked; one off the site, as the one of its kind
 * its last address now leads to, as SiteLinkedContents does on the site.
 *
 * It reaches every module a link can lead to, so its coupling and what it
 * takes grow with the kinds of content and say nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 *
 * @phpstan-import-type ExternalView from ExternalComponents
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
        private CmsExternalViews $externalViews,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content previewed, linked by no text of its own
     *
     * @return array<string, array{url: string, title: string, external?: ExternalView}>
     */
    public function __invoke(RichText $content, array $images, ?string $painted): array
    {
        return $this->linked(static fn (LinkType $type): array => SitePage::linkedIds($content, $images, $type), $painted);
    }

    /**
     * What the entries of menus lead to, as for a text: those of each kind
     * on the site, or redirected, with their address and name, keyed by id.
     *
     * @param list<array{type: string, id: string, label: ?string}> $entries
     *
     * @return array<string, array{url: string, title: string, external?: ExternalView}>
     */
    public function menuTargets(array $entries): array
    {
        return $this->linked(static fn (LinkType $type): array => array_values(array_map(
            static fn (array $entry): string => $entry['id'],
            array_filter($entries, static fn (array $entry): bool => $type->value === $entry['type']),
        )), null);
    }

    /**
     * @param \Closure(LinkType): list<string> $ids what is linked of each kind
     *
     * @return array<string, array{url: string, title: string, external?: ExternalView}>
     */
    private function linked(\Closure $ids, ?string $painted): array
    {
        return [
            ...$this->resolve(LinkType::Page, $ids(LinkType::Page), $painted, fn (array $ids): array => array_map(fn (PageOnSiteResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
            ], new PagesOnSiteFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $ids(LinkType::Post), $painted, fn (array $ids): array => array_map(function (PostOnSiteResponse $post): array {
                [$year, $month, $day] = explode('-', $post->address->day);

                return [
                    'url' => $this->urlGenerator->generate('web_post', ['section' => $post->address->section, 'year' => $year, 'month' => $month, 'day' => $day, 'slug' => $post->address->slug]),
                    'title' => $post->title,
                ];
            }, new PostsOnSiteFinder($ids)($this->postRepository))),
            ...$this->resolve(LinkType::Term, $ids(LinkType::Term), $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $section = new TermOnSiteFinder(Taxonomy::Section, $id)($this->termRepository);

                if (null !== $section) {
                    return ['url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug]), 'title' => $section->name];
                }

                $tag = new TermOnSiteFinder(Taxonomy::Tag, $id)($this->termRepository);

                return null === $tag ? null : ['url' => $this->urlGenerator->generate('web_tag', ['slug' => $tag->slug]), 'title' => $tag->name];
            })),
            ...$this->resolve(LinkType::Author, $ids(LinkType::Author), $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $author = new AuthorOnSiteFinder($id)($this->authorRepository);

                return null === $author ? null : ['url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]), 'title' => $author->name];
            })),
            // No address of the site leads to an external resource, so none is redirected.
            ...($this->externalViews)($ids(LinkType::External)),
        ];
    }

    /**
     * @param list<string>                                                             $ids
     * @param \Closure(list<string>): array<string, array{url: string, title: string}> $onSite
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function resolve(LinkType $type, array $ids, ?string $painted, \Closure $onSite): array
    {
        return new ReferenceResolver($type->value, $ids, $painted)($onSite, $this->redirectsByOrigin);
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
