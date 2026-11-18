<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermUpdater;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class TermUpdateController extends AbstractController
{
    #[Route('/{taxonomy}/{id}', name: 'cms_terms_update', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_terms_update')]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository, EventBus $eventBus): Response
    {
        try {
            new TermUpdater(
                $taxonomy->taxonomy(),
                $id,
                $request->request->getString('name'),
                $request->request->getString('slug'),
                $request->request->getString('meta_description'),
                $request->request->getString('robots'),
            )($termRepository, $eventBus);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.saved'), 'parameters' => []]);

        // The editor stays on the term, to go on with it.
        return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
    }
}
