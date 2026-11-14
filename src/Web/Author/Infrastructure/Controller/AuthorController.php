<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Web\Author\Application\AuthorFinder;
use Web\Author\Application\AuthorNotFound;
use Web\Author\Domain\AuthorRepository;

/**
 * Serves every published author under /autor/.
 */
final class AuthorController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedAuthor $publishedAuthor,
    ) {}

    #[Route('/autor/{slug}.html', name: 'web_author', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function __invoke(string $slug, AuthorRepository $authorRepository): Response
    {
        try {
            $author = new AuthorFinder($slug)($authorRepository);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->publishedAuthor)($author));
    }
}
