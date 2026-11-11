<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageUnpublisher;
use Cms\Page\Domain\PageRedirects;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\ResourceRetirement;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Infrastructure\Controller\RetirementRequest;
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
 * Takes a page off the site with what the editor chose for its addresses.
 * When a choice is refused, the form comes back with them all as they were.
 *
 * It reaches the redirects as much as the page, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PageUnpublishController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/pages/{id}/unpublish', name: 'cms_pages_unpublish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository, EventBus $eventBus, PageRedirects $pageRedirects): Response
    {
        $this->csrf->assertValid($request);

        try {
            new ResourceRetirement(
                RedirectId::random()->value(),
                Resource::page($id),
                RetirementRequest::address($request),
                RetirementRequest::incoming($request),
                static function () use ($id, $pageRepository, $eventBus, $pageRedirects): void {
                    new PageUnpublisher($id)($pageRepository, $eventBus, $pageRedirects);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the form it goes back to, with every choice as it was.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $request->getSession()->set(\sprintf(PageUnpublishFormController::SAVED, $id), $request->request->all());

            return $this->redirectToRoute('cms_pages_unpublish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.page.unpublished', 'parameters' => []]);

        // The editor stays on the page, to go on with it.
        return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
