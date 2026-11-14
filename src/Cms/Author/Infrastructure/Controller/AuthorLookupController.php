<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The authors a field holds, by their ids, for it to show their current
 * names and states. An id of no author is left out.
 */
final class AuthorLookupController extends AbstractController
{
    public function __construct(private readonly PickedAuthor $pickedAuthor) {}

    #[Route('/authors/lookup', name: 'cms_authors_lookup', methods: ['GET'])]
    public function __invoke(
        AuthorRepository $authorRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['authors' => array_map($this->pickedAuthor, new AuthorsFinder($ids)($authorRepository))]);
    }
}
