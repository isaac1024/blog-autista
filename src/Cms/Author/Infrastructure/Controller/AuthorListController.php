<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorSearcher;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The authors, searched by their name, a page of them at a time.
 */
final class AuthorListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly AuthorList $authorList,
    ) {}

    #[Route('/authors', name: 'cms_authors', methods: ['GET'])]
    public function __invoke(
        AuthorRepository $authorRepository,
        #[MapQueryParameter]
        string $name = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $authors = new AuthorSearcher($name, $page)($authorRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->authorList)($authors, $name, $page));
    }
}
