<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostUnpublisher;
use Cms\Post\Domain\PostRedirects;
use Cms\Post\Domain\PostRepository;
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
 * Takes a post off the site with what the editor chose for its addresses.
 * When a choice is refused, the form comes back with them all as they were.
 *
 * It reaches the redirects as much as the post, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PostUnpublishController extends AbstractController
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/posts/{id}/unpublish', name: 'cms_posts_unpublish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_posts_unpublish')]
    public function __invoke(string $id, Request $request, PostRepository $postRepository, EventBus $eventBus, PostRedirects $postRedirects): Response
    {
        try {
            new ResourceRetirement(
                RedirectId::random()->value(),
                Resource::post($id),
                RetirementRequest::address($request),
                RetirementRequest::incoming($request),
                function () use ($id, $postRepository, $eventBus, $postRedirects): void {
                    new PostUnpublisher($id)($postRepository, $eventBus, $this->transaction, $postRedirects);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the form it goes back to, with every choice as it was.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $request->getSession()->set(\sprintf(PostUnpublishFormController::SAVED, $id), $request->request->all());

            return $this->redirectToRoute('cms_posts_unpublish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.unpublished', 'parameters' => []]);

        // The editor stays on the post, to go on with it.
        return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
