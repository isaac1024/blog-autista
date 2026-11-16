<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Web\Page\Application\PageFinder;
use Web\Page\Application\PageNotFound;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\PostsPageNotFound;
use Web\Post\Infrastructure\Ui\SiteSearch;

/**
 * Serves every published page but the home page at its own address. The
 * page that searches the posts searches what its query asks for, a page of
 * results at a time.
 */
final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPage $publishedPage,
        private readonly SiteSearch $siteSearch,
    ) {}

    #[Route('/{slug}.html', name: 'web_page', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function __invoke(string $slug, Request $request, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($slug)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $composed = ($this->publishedPage)($page);

        if (!$page->search) {
            return $this->pageRenderer->render($composed);
        }

        $number = $request->query->getString(ListPagination::SITE_PARAMETER, (string) ListPagination::FIRST_PAGE);

        try {
            if (1 !== preg_match('/^[1-9][0-9]{0,5}$/', $number)) {
                throw PostsPageNotFound::numbered(0);
            }

            $composed = ($this->siteSearch)($composed, $slug, $request->query->getString(SiteSearch::PARAMETER), (int) $number);
        } catch (PostsPageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($composed);
    }
}
