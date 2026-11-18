<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Content;

use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\Dimension;
use Cms\Author\Application\AuthorNamesFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Application\PageTitlesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostTitlesFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Application\TermNamesFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that names what the site counts the visits of as the
 * pages, the posts, the sections and tags, and the authors of the CMS keep
 * it, telling a section from a tag by its taxonomy. It reads the names
 * alone, never the whole of each content.
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
        $named = static fn (string $kind): \Closure => static fn (string $name): array => ['name' => $name, 'kind' => $kind];

        return match ($kind) {
            Dimension::Page => array_map($named('page'), new PageTitlesFinder($ids)($this->pageRepository)),
            Dimension::Post => array_map($named('post'), new PostTitlesFinder($ids)($this->postRepository)),
            Dimension::Term => array_map(static fn (array $term): array => ['name' => $term['name'], 'kind' => $term['taxonomy']], new TermNamesFinder($ids)($this->termRepository)),
            Dimension::Author => array_map($named('author'), new AuthorNamesFinder($ids)($this->authorRepository)),
            default => [],
        };
    }
}
