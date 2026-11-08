<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageSearcher;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The pages the rich text editor offers to link, newest first, searched by
 * title, but for the page being edited: a page doesn't link itself. A full
 * page of results tells the editor there may be more.
 */
final class PageSearchController extends AbstractController
{
    public function __construct(private readonly EditorPage $editorPage) {}

    #[Route('/pages/search', name: 'cms_pages_search', methods: ['GET'])]
    public function __invoke(
        PageRepository $pageRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        ?string $except = null,
    ): JsonResponse {
        try {
            $pages = new PageSearcher($title, $page, $except)($pageRepository);
        } catch (DomainError $exception) {
            // A page number or an id that can't be: there is no such list.
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'pages' => array_map($this->editorPage, $pages),
            'more' => PageSearcher::PAGE_SIZE === \count($pages),
        ]);
    }
}
