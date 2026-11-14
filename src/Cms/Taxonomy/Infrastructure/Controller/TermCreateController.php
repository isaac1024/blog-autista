<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermCreator;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TermCreateController extends AbstractController
{
    #[Route('/{taxonomy}', name: 'cms_terms_create', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT], methods: ['POST'])]
    public function __invoke(TaxonomySegment $taxonomy, Request $request, TermRepository $termRepository, EventBus $eventBus, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        $id = TermId::random()->value();

        try {
            new TermCreator($id, $taxonomy->taxonomy(), $request->request->getString('name'))($termRepository, $eventBus);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_terms', ['taxonomy' => $taxonomy->value], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.created'), 'parameters' => []]);

        // The editor goes on with the term just created.
        return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
    }
}
