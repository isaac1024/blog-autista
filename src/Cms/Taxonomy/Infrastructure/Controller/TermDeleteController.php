<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermDeleter;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermUsage;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Deletes a term off the site, back to the list of its taxonomy.
 */
final class TermDeleteController extends AbstractController
{
    #[Route('/{taxonomy}/{id}/delete', name: 'cms_terms_delete', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_terms_delete')]
    public function __invoke(TaxonomySegment $taxonomy, string $id, TermRepository $termRepository, TermUsage $termUsage): Response
    {
        try {
            new TermDeleter($taxonomy->taxonomy(), $id)($termRepository, $termUsage);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.deleted'), 'parameters' => []]);

        return $this->redirectToRoute('cms_terms', ['taxonomy' => $taxonomy->value], Response::HTTP_SEE_OTHER);
    }
}
