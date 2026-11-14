<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shows the version of a post last saved as the site would serve it, to
 * whoever holds a signed link, the editor included, without signing in,
 * until it expires.
 */
final class PostPreviewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PreviewPost $previewPost,
        private readonly SignedUrls $signedUrls,
    ) {}

    #[Route('/preview/posts/{id}', name: 'cms_posts_preview', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository): Response
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The preview link is not valid, or has expired.');
        }

        try {
            $post = new PostFinder($id)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $response = $this->pageRenderer->render(($this->previewPost)($post));
        // A draft is never kept by browsers or caches along the way.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', PreviewPost::ROBOTS);

        return $response;
    }
}
