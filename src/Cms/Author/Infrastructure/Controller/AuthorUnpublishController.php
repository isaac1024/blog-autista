<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorUnpublisher;
use Cms\Author\Domain\AuthorRedirects;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Cms\Redirect\Application\ResourceRetirement;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\RetirementRequest;
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
 * Takes an author off the site with what the editor chose for its addresses.
 * When a choice is refused, the form comes back with them all as they were.
 *
 * It reaches the redirects as much as the author, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class AuthorUnpublishController extends AbstractController
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/authors/{id}/unpublish', name: 'cms_authors_unpublish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_authors_unpublish')]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, AuthorRedirects $authorRedirects): Response
    {
        try {
            new ResourceRetirement(
                RedirectId::random()->value(),
                Resource::author($id),
                RetirementRequest::address($request),
                RetirementRequest::incoming($request),
                function () use ($id, $authorRepository, $eventBus, $avatarStorage, $authorRedirects): void {
                    new AuthorUnpublisher($id)($authorRepository, $eventBus, $this->transaction, $avatarStorage, $authorRedirects);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the form it goes back to, with every choice as it was.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $request->getSession()->set(\sprintf(AuthorUnpublishFormController::SAVED, $id), $request->request->all());

            return $this->redirectToRoute('cms_authors_unpublish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.unpublished', 'parameters' => []]);

        // The editor stays on the author, to go on with it.
        return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
