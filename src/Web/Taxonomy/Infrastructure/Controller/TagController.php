<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public function __invoke(string $slug, TermRepository $termRepository): Response
    {
        try {
            $term = new TermFinder(Taxonomy::Tag->value, $slug)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->publishedTerm)(
            $term,
            $this->generateUrl('web_tag', ['slug' => $term->slug], UrlGeneratorInterface::ABSOLUTE_URL),
        ));
    }
}
