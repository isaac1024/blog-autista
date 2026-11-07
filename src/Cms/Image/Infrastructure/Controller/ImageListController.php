<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Image;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The image library, where images are uploaded. There are no images to list
 * yet, so it always invites to upload the first one.
 */
final class ImageListController extends AbstractController
{
    private const string UPLOAD_MODAL = 'upload-image';

    /**
     * Quality of the WebP the browser encodes: 0.85, where the loss doesn't show.
     */
    private const float QUALITY = 0.85;

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
                $this->uploadModal(),
            ])
            ->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.images.empty.title'),
                'message' => $this->translator->trans('cms.images.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::UPLOAD_MODAL,
                    'label' => $this->translator->trans('cms.images.empty.action'),
                ]),
            ]);
    }

    /**
     * The image, cropped in the browser, and its alternative text: both are
     * required.
     */
    private function uploadModal(): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::UPLOAD_MODAL,
            'trigger' => $this->translator->trans('cms.images.add'),
            'title' => $this->translator->trans('cms.images.upload.title'),
            'description' => $this->translator->trans('cms.images.upload.description'),
            'size' => 'wide',
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->generateUrl('cms_images_create'),
                'method' => 'post',
                'enctype' => 'multipart/form-data',
            ], [
                new PageComponent('Shared:ImageCropper', [
                    'name' => 'image',
                    'id' => 'image-file',
                    'label' => $this->translator->trans('cms.images.upload.image_label'),
                    'width' => Image::WIDTH,
                    'height' => Image::HEIGHT,
                    'quality' => self::QUALITY,
                ]),
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'image-alt',
                        'text' => $this->translator->trans('cms.images.upload.alt_label'),
                    ]),
                    new PageComponent('Shared:FormHint', [
                        'id' => 'image-alt-hint',
                        'text' => $this->translator->trans('cms.images.upload.alt_hint'),
                    ]),
                    new PageComponent('Shared:FormTextarea', [
                        'name' => 'alt',
                        'id' => 'image-alt',
                        'rows' => 2,
                        'recommendedMax' => Alt::MAX_LENGTH,
                        'attributes' => [
                            'required' => true,
                            'maxlength' => Alt::MAX_LENGTH,
                            'aria-describedby' => 'image-alt-hint image-alt-count',
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.images.upload.submit'),
                ]),
            ]),
        ]);
    }
}
