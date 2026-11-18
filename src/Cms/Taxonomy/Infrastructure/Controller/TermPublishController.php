<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\ResourcePublication;
use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\PublicationRequest;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Application\TermPublisher;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Publishes a term as it is saved. When that changes the redirects, it asks
 * first, on a page of its own, and publishes once the editor confirms there,
 * if publishing still does what was shown. A refused choice, or one made on
 * what changed since, goes back to that page; anything else the term lacks,
 * to its editor.
 *
 * It reaches the redirects as much as the term, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class TermPublishController extends AbstractController
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/{taxonomy}/{id}/publish', name: 'cms_terms_publish', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_terms_publish')]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository, EventBus $eventBus): Response
    {
        $resource = Resource::term($id);
        $outlook = PublicationRequest::outlook($request);
        $parameters = ['taxonomy' => $taxonomy->value, 'id' => $id];

        try {
            if (null === $outlook && new PublicationOutlookFinder($resource)($this->redirectRepository, $this->siteResources)->asksFirst) {
                return $this->redirectToRoute('cms_terms_publish_form', $parameters, Response::HTTP_SEE_OTHER);
            }

            // Straight from the editor, nothing was shown, so publishing must
            // still change no redirect when it happens.
            new ResourcePublication(
                RedirectId::random()->value(),
                $resource,
                PublicationRequest::previous($request, $resource),
                $outlook ?? '',
                static function () use ($taxonomy, $id, $termRepository, $eventBus): void {
                    new TermPublisher($taxonomy->taxonomy(), $id)($termRepository, $eventBus);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            // What concerns the redirects is told on the confirmation, with
            // the choices made there, if any, as they were.
            if ($error instanceof InvalidRedirect) {
                if (null !== $outlook) {
                    $request->getSession()->set(\sprintf(TermPublishFormController::SAVED, $id), $request->request->all());
                }

                return $this->redirectToRoute('cms_terms_publish_form', $parameters, Response::HTTP_SEE_OTHER);
            }

            // Told on the page it goes back to.
            return $this->redirectToRoute('cms_terms_edit', $parameters, Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => $taxonomy->key('notices.published'), 'parameters' => []]);

        // The editor stays on the term, to go on with it.
        return $this->redirectToRoute('cms_terms_edit', $parameters, Response::HTTP_SEE_OTHER);
    }
}
