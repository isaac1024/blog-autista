<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where a term is taken off the site: it asks what becomes of its address,
 * and of each redirect that leads to it, before it goes. A choice refused
 * comes back as it was made.
 */
final class TermUnpublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by term.
     */
    public const string SAVED = 'cms.terms.unpublish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TermUnpublishForm $termUnpublishForm,
    ) {}

    #[Route('/{taxonomy}/{id}/unpublish', name: 'cms_terms_unpublish_form', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository): Response
    {
        try {
            $term = new TermFinder($taxonomy->taxonomy(), $id)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!$term->canBeUnpublished) {
            $this->addFlash('error', ['key' => 'errors.term.publication.not_published', 'parameters' => []]);

            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->termUnpublishForm)($taxonomy, $term, \is_array($saved) ? $saved : []));
    }
}
