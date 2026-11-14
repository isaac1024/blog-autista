<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where a post is taken off the site: it asks what becomes of its address,
 * and of each redirect that leads to it, before it goes. A choice refused
 * comes back as it was made.
 */
final class PostUnpublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by post.
     */
    public const string SAVED = 'cms.posts.unpublish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PostUnpublishForm $postUnpublishForm,
    ) {}

    #[Route('/posts/{id}/unpublish', name: 'cms_posts_unpublish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository): Response
    {
        try {
            $post = new PostFinder($id)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!$post->canBeUnpublished) {
            $this->addFlash('error', ['key' => 'errors.post.publication.not_published', 'parameters' => []]);

            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->postUnpublishForm)($post, \is_array($saved) ? $saved : []));
    }
}
