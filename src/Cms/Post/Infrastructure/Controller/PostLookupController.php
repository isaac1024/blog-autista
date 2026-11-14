<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The posts a field holds, by their ids, for it to show their current
 * titles and states. An id of no post is left out.
 */
final class PostLookupController extends AbstractController
{
    public function __construct(private readonly PickedPost $pickedPost) {}

    #[Route('/posts/lookup', name: 'cms_posts_lookup', methods: ['GET'])]
    public function __invoke(
        PostRepository $postRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['posts' => array_map($this->pickedPost, new PostsFinder($ids)($postRepository))]);
    }
}
