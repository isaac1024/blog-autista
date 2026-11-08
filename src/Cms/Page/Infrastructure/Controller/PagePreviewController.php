<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shows the version of a page last saved as the site would serve it, to
 * whoever holds a signed link, the editor included, without signing in, until
 * it expires.
 */
final class PagePreviewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PreviewPage $previewPage,
        private readonly SignedUrls $signedUrls,
    ) {}

    #[Route('/preview/{id}', name: 'cms_pages_preview', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository): Response
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The preview link is not valid, or has expired.');
        }

        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $response = $this->pageRenderer->render(($this->previewPage)($page));
        // A draft is never kept by browsers or caches along the way.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', PreviewPage::ROBOTS);

        return $response;
    }
}
