<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostSearcher;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The posts whose title has every word searched, newest first, a page of
 * them at a time, as JSON, to pick from, such as where a redirect leads or
 * a text links, but for the one given: a post doesn't link itself.
 */
final class PostSearchController extends AbstractController
{
    public function __construct(private readonly PickedPost $pickedPost) {}

    #[Route('/posts/search', name: 'cms_posts_search', methods: ['GET'])]
    public function __invoke(
        PostRepository $postRepository,
        #[MapQueryParameter]
        string $title = '',
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        ?string $except = null,
    ): JsonResponse {
        try {
            $posts = new PostSearcher($title, $page, $except)($postRepository);
        } catch (DomainError $exception) {
            // A page number or an id that can't be: there is no such list.
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'posts' => array_map($this->pickedPost, $posts->posts),
            'more' => $posts->hasMore,
        ]);
    }
}
