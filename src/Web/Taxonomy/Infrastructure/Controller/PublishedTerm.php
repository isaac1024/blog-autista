<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteTermPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Web\Post\Application\PostLister;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Post\Infrastructure\Ui\SitePostCards;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermResponse;

/**
 * Composes a page of the list of a term of the site, painted as SiteTermPage
 * paints it, in the chrome of the site: its posts, a page of them at a time,
 * each page presenting itself to search engines with its own address as its
 * canonical URL, and titled by its number past the first.
 *
 * @throws \Web\Post\Application\PostsPageNotFound for a page that can't be
 */
final readonly class PublishedTerm
{
    public function __construct(
        private SiteLayout $siteLayout,
        private SiteTermPage $siteTermPage,
        private PostRepository $postRepository,
        private SitePostCards $postCards,
        private ListPagination $pagination,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param string $route the route of the pages of the term, such as web_section
     */
    public function __invoke(TermResponse $term, string $route, PostSelection $selection, int $page): Page
    {
        $posts = new PostLister($selection, $page)($this->postRepository);
        $parameters = ['slug' => $term->slug];
        $title = ListPagination::FIRST_PAGE === $page ? $term->name : $this->translator->trans('site.posts.page_title', ['%name%' => $term->name, '%page%' => $page]);

        return ($this->siteTermPage)(
            $this->siteLayout->page($title)
                ->withSearchEngineMetadata($term->metaDescription, $term->robots, $this->pagination->siteUrl($route, $parameters, $page, UrlGeneratorInterface::ABSOLUTE_URL))
                ->withCountedVisits('term', $term->id),
            $term->name,
            ($this->postCards)($posts->posts),
            $this->pagination->site($route, $parameters, $page, $posts->hasMore),
        );
    }
}
