<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostNotFound;
use Cms\Post\Application\PostUpdater;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Shared\Infrastructure\RichText\TiptapRichText;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Saves what is edited on a post, then goes back to its editor.
 */
final class PostUpdateController extends AbstractController
{
    public function __construct(private readonly TiptapRichText $tiptapRichText) {}

    #[Route('/posts/{id}', name: 'cms_posts_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, PostRepository $postRepository, EventBus $eventBus, Classifiers $classifiers, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new PostUpdater(
                $id,
                $request->request->getString('title'),
                $request->request->getString('slug'),
                $request->request->getString('section'),
                $request->request->getString('author'),
                self::tags($request),
                // The editor posts its own document, which only this adapter knows.
                $this->tiptapRichText->toStored($request->request->getString('content')),
                $request->request->getString('meta_description'),
                $request->request->getString('robots'),
            )($postRepository, $eventBus, $classifiers);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.post.saved', 'parameters' => []]);

        // The editor stays on the post, to go on with it.
        return $this->redirectToRoute('cms_posts_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    /**
     * The ids of the tags posted, in order; anything but text is left out.
     *
     * @return list<string>
     */
    private static function tags(Request $request): array
    {
        $tags = $request->request->all('tags');

        return array_values(array_filter($tags, static fn (mixed $tag): bool => \is_string($tag)));
    }
}
