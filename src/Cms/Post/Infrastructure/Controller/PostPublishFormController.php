<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where publishing a post is confirmed when it changes the redirects: what
 * goes at the address it takes, and what the address it leaves does. A post
 * whose publishing changes none goes back to its editor, which publishes it
 * at once.
 */
final class PostPublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by post.
     */
    public const string SAVED = 'cms.posts.publish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PostPublishForm $postPublishForm,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
    ) {}

    #[Route('/posts/{id}/publish', name: 'cms_posts_publish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository): Response
    {
        try {
            $post = new PostFinder($id)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $outlook = new PublicationOutlookFinder(Resource::post($id))($this->redirectRepository, $this->siteResources);

        if (!$post->hasSomethingToPublish || !$outlook->asksFirst) {
            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->postPublishForm)($post, $outlook, \is_array($saved) ? $saved : []));
    }
}
