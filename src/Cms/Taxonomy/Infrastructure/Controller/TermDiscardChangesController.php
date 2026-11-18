<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermChangesDiscarder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Goes back to the editor, which shows the term as it is on the site again.
 */
final class TermDiscardChangesController extends AbstractController
{
    #[Route('/{taxonomy}/{id}/discard', name: 'cms_terms_discard', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_terms_discard')]
    public function __invoke(TaxonomySegment $taxonomy, string $id, TermRepository $termRepository, EventBus $eventBus): Response
    {
        try {
            new TermChangesDiscarder($taxonomy->taxonomy(), $id)($termRepository, $eventBus);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.discarded'), 'parameters' => []]);

        return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
    }
}
