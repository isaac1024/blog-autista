<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Site;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Analytics\Domain\Content;
use Web\Analytics\Domain\ContentType;
use Web\Analytics\Domain\PublishedContents;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Page\Application\PagesOnSiteFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostOnSiteFinder;
use Web\Post\Domain\PostRepository;
use Web\Taxonomy\Application\TermOnSiteFinder;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Outbound adapter that asks the pages, the posts, the sections and tags,
 * and the authors the site serves whether what a visit was to is on it.
 */
#[AsAlias(PublishedContents::class)]
final readonly class SitePublishedContents implements PublishedContents
{
    public function __construct(
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
    ) {}

    #[\Override]
    public function contains(Content $content): bool
    {
        $id = $content->id()->value();

        return match ($content->type()) {
            ContentType::Page => [] !== new PagesOnSiteFinder([$id])($this->pageRepository),
            ContentType::Post => null !== new PostOnSiteFinder($id)($this->postRepository),
            ContentType::Term => null !== new TermOnSiteFinder(Taxonomy::Section->value, $id)($this->termRepository)
                || null !== new TermOnSiteFinder(Taxonomy::Tag->value, $id)($this->termRepository),
            ContentType::Author => null !== new AuthorOnSiteFinder($id)($this->authorRepository),
        };
    }
}
