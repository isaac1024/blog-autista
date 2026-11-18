<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostDeleter;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostUsage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Deletes a post off the site, then goes back to the list of posts.
 */
final class PostDeleteController extends AbstractController
{
    #[Route('/posts/{id}/delete', name: 'cms_posts_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_posts_delete')]
    public function __invoke(string $id, PostRepository $postRepository, EventBus $eventBus, PostUsage $postUsage): Response
    {
        try {
            new PostDeleter($id)($postRepository, $eventBus, $postUsage);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_posts', status: Response::HTTP_SEE_OTHER);
    }
}
