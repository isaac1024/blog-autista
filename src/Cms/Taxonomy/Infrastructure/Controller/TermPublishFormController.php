<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Taxonomy\Application\PostsLeftBehindCounter;
use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\PostsUnderSectionSlugs;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where publishing a term is confirmed when it changes the redirects: what
 * goes at the address it takes, and what the address it leaves does. A term
 * whose publishing changes none goes back to its editor, which publishes it
 * at once.
 */
final class TermPublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by term.
     */
    public const string SAVED = 'cms.terms.publish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TermPublishForm $termPublishForm,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
    ) {}

    #[Route('/{taxonomy}/{id}/publish', name: 'cms_terms_publish_form', requirements: ['taxonomy' => TaxonomySegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(TaxonomySegment $taxonomy, string $id, Request $request, TermRepository $termRepository, PostsUnderSectionSlugs $postsUnderSectionSlugs): Response
    {
        try {
            $term = new TermFinder($taxonomy->taxonomy(), $id)($termRepository);
        } catch (TermNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $outlook = new PublicationOutlookFinder(Resource::term($id))($this->redirectRepository, $this->siteResources);

        if (!$term->hasSomethingToPublish || !$outlook->asksFirst) {
            return $this->redirectToRoute('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        $leftBehind = new PostsLeftBehindCounter($taxonomy->taxonomy(), $id)($termRepository, $postsUnderSectionSlugs);

        return $this->pageRenderer->render(($this->termPublishForm)($taxonomy, $term, $outlook, \is_array($saved) ? $saved : [], $leftBehind));
    }
}
