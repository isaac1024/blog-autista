<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Web\Post\Application\PostsPageNotFound;
use Web\Taxonomy\Application\TermFinder;
use Web\Taxonomy\Application\TermNotFound;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Serves every published tag under /tag/.
 */
final class TagController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedTerm $publishedTerm,
    ) {}

    #[Route('/tag/{slug}.html', name: 'web_tag', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function __invoke(string $slug, TermRepository $termRepository, #[MapQueryParameter] int $pagina = 1): Response
    {
        try {
            $term = new TermFinder(Taxonomy::Tag->value, $slug)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        try {
            $page = ($this->publishedTerm)($term, 'web_tag', $pagina);
        } catch (PostsPageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($page);
    }
}
