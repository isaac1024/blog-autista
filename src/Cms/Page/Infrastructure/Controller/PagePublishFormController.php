<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Infrastructure\Controller\PublicationForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where publishing a page is confirmed when it changes the redirects: what
 * goes at the address it takes, and what the address it leaves does. A page
 * whose publishing changes none goes back to its editor, which publishes it
 * at once.
 *
 * It reaches the redirects as much as the page, so its coupling says nothing
 * about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PagePublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by page.
     */
    public const string SAVED = 'cms.pages.publish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly PublicationForm $publicationForm,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
    ) {}

    #[Route('/pages/{id}/publish', name: 'cms_pages_publish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $outlook = new PublicationOutlookFinder(Resource::page($id))($this->redirectRepository, $this->siteResources);

        if (!$page->hasSomethingToPublish || !$outlook->asksFirst()) {
            return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));
        $title = $this->translator->trans('cms.pages.publish.page_title', ['%title%' => $page->title]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.publish.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_pages_edit', ['id' => $id]),
                    'label' => $this->translator->trans('cms.pages.unpublish.back'),
                ]),
            ]);

        foreach (($this->publicationForm)(
            $outlook,
            $this->generateUrl('cms_pages_publish', ['id' => $id]),
            'cms_pages_publish',
            $this->translator->trans('cms.pages.publish.submit'),
            \is_array($saved) ? $saved : [],
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $this->pageRenderer->render($layout);
    }
}
