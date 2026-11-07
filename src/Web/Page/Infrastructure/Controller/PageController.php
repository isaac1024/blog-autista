<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Web\Page\Application\PageFinder;
use Web\Page\Application\PageNotFound;
use Web\Page\Domain\PageRepository;

/**
 * Serves every published page but the home page at its own address.
 */
final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPage $publishedPage,
    ) {}

    #[Route('/{slug}.html', name: 'web_page', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function __invoke(string $slug, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($slug)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->publishedPage)($page));
    }
}
