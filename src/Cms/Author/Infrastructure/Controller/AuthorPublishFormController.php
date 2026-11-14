<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
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
 * Where publishing an author is confirmed when it changes the redirects:
 * what goes at the address it takes, and what the address it leaves does.
 * An author whose publishing changes none goes back to its editor, which
 * publishes it at once.
 */
final class AuthorPublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by author.
     */
    public const string SAVED = 'cms.authors.publish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly AuthorPublishForm $authorPublishForm,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
    ) {}

    #[Route('/authors/{id}/publish', name: 'cms_authors_publish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository): Response
    {
        try {
            $author = new AuthorFinder($id)($authorRepository);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $outlook = new PublicationOutlookFinder(Resource::author($id))($this->redirectRepository, $this->siteResources);

        if (!$author->hasSomethingToPublish || !$outlook->asksFirst) {
            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->authorPublishForm)($author, $outlook, \is_array($saved) ? $saved : []));
    }
}
