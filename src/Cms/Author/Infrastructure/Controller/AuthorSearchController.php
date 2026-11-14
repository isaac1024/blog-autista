<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorSearcher;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The authors whose name has what is searched, newest first, a page of them
 * at a time, as JSON, to pick from, such as where a redirect leads.
 */
final class AuthorSearchController extends AbstractController
{
    public function __construct(private readonly PickedAuthor $pickedAuthor) {}

    #[Route('/authors/search', name: 'cms_authors_search', methods: ['GET'])]
    public function __invoke(
        AuthorRepository $authorRepository,
        #[MapQueryParameter]
        string $name = '',
        #[MapQueryParameter]
        int $page = 1,
    ): JsonResponse {
        try {
            $authors = new AuthorSearcher($name, $page)($authorRepository);
        } catch (DomainError $exception) {
            // A page number that can't be: there is no such list.
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'authors' => array_map($this->pickedAuthor, $authors->authors),
            'more' => $authors->hasMore,
        ]);
    }
}
