<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Web\Page\Application\HomePageFinder;
use Web\Page\Application\PageNotFound;
use Web\Page\Domain\PageRepository;

/**
 * Serves the home page at the root of the site, once it is published.
 */
final class HomePageController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPage $publishedPage,
    ) {}

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function __invoke(PageRepository $pageRepository): Response
    {
        try {
            $page = new HomePageFinder()($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->publishedPage)($page));
    }
}
