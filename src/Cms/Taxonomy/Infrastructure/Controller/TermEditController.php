<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Clock;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The editor of a term, with a link to its preview that anyone can open for
 * a while.
 */
final class TermEditController extends AbstractController
{
    /**
     * How long a shared preview link lets anyone see the term.
     */
    private const string SHARED_PREVIEW_VALIDITY = 'P7D';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TermEditor $termEditor,
    ) {}

    #[Route('/{taxonomy}/{id}', name: 'cms_terms_edit', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, TermRepository $termRepository, SignedUrls $signedUrls): Response
    {
        try {
            $term = new TermFinder($taxonomy->taxonomy(), $id)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Signed anew each time the editor loads, valid for a while from now.
        $expiresAt = Clock::now()->add(new \DateInterval(self::SHARED_PREVIEW_VALIDITY));
        $previewUrl = $signedUrls->sign(
            $this->generateUrl('cms_terms_preview', ['taxonomy' => $taxonomy->value, 'id' => $term->id], UrlGeneratorInterface::ABSOLUTE_URL),
            $expiresAt,
        );

        return $this->pageRenderer->render(($this->termEditor)($taxonomy, $term, $previewUrl, $expiresAt));
    }
}
