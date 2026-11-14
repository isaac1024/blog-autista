<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where an author is taken off the site: it asks what becomes of its address,
 * and of each redirect that leads to it, before it goes. A choice refused
 * comes back as it was made.
 */
final class AuthorUnpublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by author.
     */
    public const string SAVED = 'cms.authors.unpublish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly AuthorUnpublishForm $authorUnpublishForm,
    ) {}

    #[Route('/authors/{id}/unpublish', name: 'cms_authors_unpublish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository): Response
    {
        try {
            $author = new AuthorFinder($id)($authorRepository);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!$author->canBeUnpublished) {
            $this->addFlash('error', ['key' => 'errors.author.publication.not_published', 'parameters' => []]);

            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->authorUnpublishForm)($author, \is_array($saved) ? $saved : []));
    }
}
