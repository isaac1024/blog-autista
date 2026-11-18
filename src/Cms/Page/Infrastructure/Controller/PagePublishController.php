<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PagePublisher;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\ResourcePublication;
use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\PublicationRequest;
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
 * Publishes a page as it is saved. When that changes the redirects, it asks
 * first, on a page of its own, and publishes once the editor confirms there,
 * if publishing still does what was shown. A refused choice, or one made on
 * what changed since, goes back to that page; anything else the page lacks,
 * to its editor.
 *
 * It reaches the redirects as much as the page, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PagePublishController extends AbstractController
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/pages/{id}/publish', name: 'cms_pages_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_pages_publish')]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository, EventBus $eventBus): Response
    {
        $resource = Resource::page($id);
        $outlook = PublicationRequest::outlook($request);

        try {
            if (null === $outlook && new PublicationOutlookFinder($resource)($this->redirectRepository, $this->siteResources)->asksFirst) {
                return $this->redirectToRoute('cms_pages_publish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
            }

            // Straight from the editor, nothing was shown, so publishing must
            // still change no redirect when it happens.
            new ResourcePublication(
                RedirectId::random()->value(),
                $resource,
                PublicationRequest::previous($request, $resource),
                $outlook ?? '',
                static function () use ($id, $pageRepository, $eventBus): void {
                    new PagePublisher($id)($pageRepository, $eventBus);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            // What concerns the redirects is told on the confirmation, with
            // the choices made there, if any, as they were.
            if ($error instanceof InvalidRedirect) {
                if (null !== $outlook) {
                    $request->getSession()->set(\sprintf(PagePublishFormController::SAVED, $id), $request->request->all());
                }

                return $this->redirectToRoute('cms_pages_publish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
            }

            // Told on the page it goes back to.
            return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.page.published', 'parameters' => []]);

        // The editor stays on the page, to go on with it.
        return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
