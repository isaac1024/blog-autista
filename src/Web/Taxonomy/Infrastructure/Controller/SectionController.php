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
 * Serves every published section at the first level of the site.
 */
final class SectionController extends AbstractController
{
    /**
     * A slug as long as the CMS lets a slug be, at least three characters,
     * so the shorter paths of the site, such as /v, answer only themselves.
     */
    private const string SLUG = '(?=[a-z0-9-]{3})[a-z0-9]+(?:-[a-z0-9]+)*';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedTerm $publishedTerm,
    ) {}

    #[Route('/{slug}/', name: 'web_section', requirements: ['slug' => self::SLUG], methods: ['GET'])]
    public function __invoke(string $slug, TermRepository $termRepository, #[MapQueryParameter] int $pagina = 1): Response
    {
        try {
            $term = new TermFinder(Taxonomy::Section->value, $slug)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        try {
            $page = ($this->publishedTerm)($term, 'web_section', $pagina);
        } catch (PostsPageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($page);
    }
}
