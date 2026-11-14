<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermUnpublisher;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Takes a term off the site: its address there answers nothing from then on.
 */
final class TermUnpublishController extends AbstractController
{
    #[Route('/{taxonomy}/{id}/unpublish', name: 'cms_terms_unpublish', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository, EventBus $eventBus, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new TermUnpublisher($taxonomy->taxonomy(), $id)($termRepository, $eventBus);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.unpublished'), 'parameters' => []]);

        return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
    }
}
