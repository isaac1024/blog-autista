<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where a new image is added. For now only its title: the crop and the upload
 * come next.
 */
final class ImageNewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/images/new', name: 'cms_images_new', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->pageRenderer->render(
            CmsLayout::page(\sprintf(
                '%s · blog autista',
                $this->translator->trans('cms.images.new.title'),
            ))
                ->withMain('Shared:PageTitle', [
                    'title' => $this->translator->trans('cms.images.new.title'),
                    'subtitle' => $this->translator->trans('cms.images.new.subtitle'),
                ]),
        );
    }
}
