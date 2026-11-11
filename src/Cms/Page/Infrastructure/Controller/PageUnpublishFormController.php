<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where a page is taken off the site: it asks what becomes of its address,
 * and of each redirect that leads to it, before it goes. A choice refused
 * comes back as it was made.
 */
final class PageUnpublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by page.
     */
    public const string SAVED = 'cms.pages.unpublish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PageUnpublishForm $pageUnpublishForm,
    ) {}

    #[Route('/pages/{id}/unpublish', name: 'cms_pages_unpublish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!$page->canBeUnpublished) {
            $this->addFlash('error', ['key' => 'errors.page.publication.not_published', 'parameters' => []]);

            return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));

        return $this->pageRenderer->render(($this->pageUnpublishForm)($page, \is_array($saved) ? $saved : []));
    }
}
