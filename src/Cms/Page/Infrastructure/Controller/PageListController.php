<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The pages, searched by their title, a page of them at a time.
 */
final class PageListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PageList $pageList,
    ) {}

    #[Route('/pages', name: 'cms_pages', methods: ['GET'])]
    public function __invoke(
        PageRepository $pageRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $pages = new PageSearcher($title, $page, except: null)($pageRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->pageList)($pages, $title, $page));
    }
}
