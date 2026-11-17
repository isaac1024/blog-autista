<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostAddressChecker;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostPublisher;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostImages;
use Cms\Post\Domain\PostRepository;
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
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Publishes a post as it is saved. When that changes the redirects, it asks
 * first, on a page of its own, and publishes once the editor confirms there,
 * if publishing still does what was shown. A refused choice, or one made on
 * what changed since, goes back to that page; anything else the post lacks,
 * or an address another post on the site has, to its editor.
 *
 * It reaches the redirects as much as the post, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PostPublishController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly Classifiers $classifiers,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/posts/{id}/publish', name: 'cms_posts_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository, EventBus $eventBus, PostImages $postImages): Response
    {
        $this->csrf->assertValid($request);

        $resource = Resource::post($id);
        $outlook = PublicationRequest::outlook($request);
        $classifiers = $this->classifiers;
        $mutualExclusion = $this->mutualExclusion;

        try {
            if (null === $outlook && new PublicationOutlookFinder($resource)($this->redirectRepository, $this->siteResources)->asksFirst) {
                // Nothing is asked of a publication another post's address
                // refuses: that is told on the editor.
                new PostAddressChecker($id)($postRepository, $classifiers);

                return $this->redirectToRoute('cms_posts_publish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
            }

            // Straight from the editor, nothing was shown, so publishing must
            // still change no redirect when it happens.
            new ResourcePublication(
                RedirectId::random()->value(),
                $resource,
                PublicationRequest::previous($request, $resource),
                $outlook ?? '',
                static function () use ($id, $postRepository, $eventBus, $classifiers, $postImages, $mutualExclusion): void {
                    new PostPublisher($id)($postRepository, $eventBus, $classifiers, $postImages, $mutualExclusion);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            // What concerns the redirects is told on the confirmation, with
            // the choices made there, if any, as they were.
            if ($error instanceof InvalidRedirect) {
                if (null !== $outlook) {
                    $request->getSession()->set(\sprintf(PostPublishFormController::SAVED, $id), $request->request->all());
                }

                return $this->redirectToRoute('cms_posts_publish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
            }

            // Told on the page it goes back to.
            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.published', 'parameters' => []]);

        // The editor stays on the post, to go on with it.
        return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
