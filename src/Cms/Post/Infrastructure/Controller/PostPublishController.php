<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostPublisher;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Publishes a post as it is saved; what it lacks, or an address another
 * post on the site has, is told on its editor.
 */
final class PostPublishController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly Classifiers $classifiers,
        private readonly MutualExclusion $mutualExclusion,
    ) {}

    #[Route('/posts/{id}/publish', name: 'cms_posts_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository, EventBus $eventBus): Response
    {
        $this->csrf->assertValid($request);

        try {
            new PostPublisher($id)($postRepository, $eventBus, $this->classifiers, $this->mutualExclusion);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.published', 'parameters' => []]);

        return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
