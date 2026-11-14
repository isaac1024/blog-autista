<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermSearcher;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The terms of a taxonomy, searched by their name, a page of them at a time.
 */
final class TermListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TermList $termList,
    ) {}

    #[Route('/{taxonomy}', name: 'cms_terms', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT], methods: ['GET'])]
    public function __invoke(
        TaxonomySegment $taxonomy,
        TermRepository $termRepository,
        #[MapQueryParameter]
        string $name = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $terms = new TermSearcher($taxonomy->taxonomy(), $name, $page)($termRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->termList)($taxonomy, $terms, $name, $page));
    }
}
