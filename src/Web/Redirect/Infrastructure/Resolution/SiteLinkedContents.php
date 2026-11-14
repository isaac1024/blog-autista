<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Resolution;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostOnSiteFinder;
use Web\Post\Domain\PostRepository;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * What a rich text painted on the site links to, its widgets and the
 * captions of its images too: the pages, posts, sections, tags and authors
 * on the site, each with its current address and name, keyed by the id
 * linked; one off the site, as the one of its kind its last address now
 * leads to. One neither on the site nor redirected isn't there, so its text
 * stays plain and its widget isn't painted; nor is the content painted,
 * which doesn't link itself.
 *
 * It reaches every module a link can lead to, so its coupling grows with
 * the kinds of content and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class SiteLinkedContents
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content painted, linked by no text of its own
     *
     * @return array<string, array{url: string, title: string}>
     */
    public function __invoke(RichText $content, array $images, ?string $painted): array
    {
        return [
            ...$this->resolve(LinkType::Page, $content, $images, $painted, fn (array $ids): array => array_map(fn (PageResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
            ], new PublishedPagesFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $content, $images, $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $post = new PostOnSiteFinder($id)($this->postRepository);

                if (null === $post) {
                    return null;
                }

                [$year, $month, $day] = explode('-', $post->day);

                return ['url' => $this->urlGenerator->generate('web_post', ['section' => $post->sectionSlug, 'year' => $year, 'month' => $month, 'day' => $day, 'slug' => $post->slug]), 'title' => $post->title];
            })),
            ...$this->resolve(LinkType::Term, $content, $images, $painted, fn (array $ids): array => $this->each($ids, function (string $id): ?array {
                $section = new TermOnSiteFinder(Taxonomy::Section->value, $id)($this->termRepository);

                if (null !== $section) {
                    return ['url' => $this->urlGenerator->generate('web_section', ['slug' => $section->slug]), 'title' => $section->name];
                }

                $tag = new TermOnSiteFinder(Taxonomy::Tag->value, $id)($this->termRepository);

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
