<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostCreator;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Creates a post from its title, then opens its editor.
 */
final class PostCreateController extends AbstractController
{
    #[Route('/posts', name: 'cms_posts_create', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_posts_create')]
    public function __invoke(Request $request, PostRepository $postRepository, EventBus $eventBus, Transaction $transaction): Response
    {
        $id = PostId::random()->value();

        try {
            new PostCreator($id, $request->request->getString('title'))($postRepository, $eventBus, $transaction);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_posts', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.created', 'parameters' => []]);

        // The editor goes on with the post just created.
        return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
