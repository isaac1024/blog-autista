<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shows the version of an author last saved as the site would serve it, to
 * whoever holds a signed link, the editor included, without signing in,
 * until it expires.
 */
final class AuthorPreviewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PreviewAuthor $previewAuthor,
        private readonly SignedUrls $signedUrls,
    ) {}

    #[Route('/preview/authors/{id}', name: 'cms_authors_preview', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository): Response
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The preview link is not valid, or has expired.');
        }

        try {
            $author = new AuthorFinder($id)($authorRepository);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $response = $this->pageRenderer->render(($this->previewAuthor)($author));
        // A draft is never kept by browsers or caches along the way.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', PreviewAuthor::ROBOTS);

        return $response;
    }
}
