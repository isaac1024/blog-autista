<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The image library. There are no images to list yet, so it always invites
 * to add the first one.
 */
final class ImageListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/images', name: 'cms_images', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->pageRenderer->render($this->page());
    }

    private function page(): Page
    {
        return CmsLayout::page(\sprintf(
            '%s · blog autista',
            $this->translator->trans('cms.images.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.images.title'),
                'subtitle' => $this->translator->trans('cms.images.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_images_new'),
                    'label' => $this->translator->trans('cms.images.add'),
                ]),
            ])
            ->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.images.empty.title'),
                'message' => $this->translator->trans('cms.images.empty.message'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_images_new'),
                    'label' => $this->translator->trans('cms.images.empty.action'),
                ]),
            ]);
    }
}
