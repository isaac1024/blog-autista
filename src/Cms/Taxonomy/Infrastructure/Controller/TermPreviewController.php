<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shows the version of a term last saved as the site would serve it, to
 * whoever holds a signed link, the editor included, without signing in, until
 * it expires.
 */
final class TermPreviewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PreviewTerm $previewTerm,
        private readonly SignedUrls $signedUrls,
    ) {}

    #[Route('/preview/{taxonomy}/{id}', name: 'cms_terms_preview', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository): Response
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The preview link is not valid, or has expired.');
        }

        try {
            $term = new TermFinder($taxonomy->taxonomy(), $id)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $response = $this->pageRenderer->render(($this->previewTerm)($taxonomy, $term));
        // A draft is never kept by browsers or caches along the way.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', PreviewTerm::ROBOTS);

        return $response;
    }
}
