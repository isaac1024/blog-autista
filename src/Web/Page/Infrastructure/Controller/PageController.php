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
use Web\Page\Domain\PageType;
use Web\Post\Application\PostsPageNotFound;
use Web\Shared\Infrastructure\Ui\SiteSearch;

/**
 * Serves every published page but the home page at its own address. The
 * page that searches the posts searches what its query asks for, a page of
 * results at a time, as often as a reader may: past that, it asks them to
 * wait, with what they wrote kept in the field.
 */
final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPage $publishedPage,
        private readonly SiteSearch $siteSearch,
        private readonly SearchRateLimit $searchRateLimit,
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

        if (PageType::Search->value !== $page->type) {
            return $this->pageRenderer->render($composed);
        }

        $number = $request->query->getString(ListPagination::SITE_PARAMETER, (string) ListPagination::FIRST_PAGE);
        $text = $request->query->getString(SiteSearch::PARAMETER);

        // Only what searches counts: the page alone asks nothing of the posts.
        if ('' !== trim($text) && !$this->searchRateLimit->accepts((string) $request->getClientIp())) {
            return $this->pageRenderer->render($this->siteSearch->limited($composed, $slug, $text))
                ->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            if (1 !== preg_match('/^[1-9][0-9]{0,5}$/', $number)) {
                throw PostsPageNotFound::numbered(0);
            }

            $composed = ($this->siteSearch)($composed, $slug, $text, (int) $number);
        } catch (PostsPageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($composed);
    }
}
