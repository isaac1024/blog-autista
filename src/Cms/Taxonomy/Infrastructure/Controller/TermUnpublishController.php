<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Redirect\Application\ResourceRetirement;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Infrastructure\Controller\RetirementRequest;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermUnpublisher;
use Cms\Taxonomy\Domain\TermRedirects;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Takes a term off the site with what the editor chose for its addresses.
 * When a choice is refused, the form comes back with them all as they were.
 *
 * It reaches the redirects as much as the term, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class TermUnpublishController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/{taxonomy}/{id}/unpublish', name: 'cms_terms_unpublish', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository, EventBus $eventBus, TermRedirects $termRedirects): Response
    {
        $this->csrf->assertValid($request);

        $parameters = ['taxonomy' => $taxonomy->value, 'id' => $id];

        try {
            new ResourceRetirement(
                RedirectId::random()->value(),
                Resource::term($id),
                RetirementRequest::address($request),
                RetirementRequest::incoming($request),
                static function () use ($taxonomy, $id, $termRepository, $eventBus, $termRedirects): void {
                    new TermUnpublisher($taxonomy->taxonomy(), $id)($termRepository, $eventBus, $termRedirects);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the form it goes back to, with every choice as it was.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $request->getSession()->set(\sprintf(TermUnpublishFormController::SAVED, $id), $request->request->all());

            return $this->redirectToRoute('cms_terms_unpublish_form', $parameters, Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.unpublished'), 'parameters' => []]);

        // The editor stays on the term, to go on with it.
        return $this->redirectToRoute('cms_terms_edit', $parameters, Response::HTTP_SEE_OTHER);
    }
}
