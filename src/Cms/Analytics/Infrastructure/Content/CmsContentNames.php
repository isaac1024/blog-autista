<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Content;

use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\Dimension;
use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that names what the site counts the visits of as the
 * pages, the posts, the sections and tags, and the authors of the CMS keep
 * it, telling a section from a tag by its taxonomy.
 */
#[AsAlias(ContentNames::class)]
final readonly class CmsContentNames implements ContentNames
{
    public function __construct(
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
    ) {}

    #[\Override]
    public function namesOf(Dimension $kind, array $ids): array
    {
        $names = [];

        foreach (match ($kind) {
            Dimension::Page => array_map(static fn (PageResponse $page): array => [$page->id, $page->title, 'page'], new PagesFinder($ids)($this->pageRepository)),
            Dimension::Post => array_map(static fn (PostResponse $post): array => [$post->id, $post->title, 'post'], new PostsFinder($ids)($this->postRepository)),
            Dimension::Term => array_map(static fn (TermResponse $term): array => [$term->id, $term->name, $term->taxonomy], new TermsFinder($ids)($this->termRepository)),
            Dimension::Author => array_map(static fn (AuthorResponse $author): array => [$author->id, $author->name, 'author'], new AuthorsFinder($ids)($this->authorRepository)),
            default => [],
        } as [$id, $name, $named]) {
            $names[$id] = ['name' => $name, 'kind' => $named];
        }

        return $names;
    }
}
