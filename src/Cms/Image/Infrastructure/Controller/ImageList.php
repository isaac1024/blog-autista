<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesResponse;
use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Image;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the image library, where images are uploaded, listed newest
 * first and searched by their alternative text, and what to do when there
 * is none to list.
 */
final readonly class ImageList
{
    private const string UPLOAD_MODAL = 'upload-image';

    /**
     * Quality of the WebP the browser encodes: 0.85, where the loss doesn't show.
     */
    private const float QUALITY = 0.85;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ImageState $imageState,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(ImagesResponse $found, string $search, int $number): Page
    {
        // The search box keeps what was searched, as the search took it.
        $alt = trim($search);
        $page = CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans('cms.images.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.images.title'),
                'subtitle' => $this->translator->trans('cms.images.subtitle'),
            ], [
                $this->uploadModal(),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_images'),
                'name' => 'alt',
                'id' => 'image-search',
                'label' => $this->translator->trans('cms.images.search.label'),
                'submit' => $this->translator->trans('cms.images.search.submit'),
                'value' => $alt,
            ]);

        if ([] === $found->images && '' === $alt) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.images.empty.title'),
                'message' => $this->translator->trans('cms.images.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::UPLOAD_MODAL,
                    'label' => $this->translator->trans('cms.images.empty.action'),
                ]),
            ]);
        }

        if ([] === $found->images) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.images.search.empty.title'),
                'message' => $this->translator->trans('cms.images.search.empty.message', ['%alt%' => $alt]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_images'),
                    'label' => $this->translator->trans('cms.images.search.empty.action'),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.images.list.label'),
        ], array_map($this->row(...), $found->images))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_images',
                '' === $alt ? [] : ['alt' => $alt],
                $number,
                $found->hasMore,
            ));
    }

    /**
     * An image by its thumbnail, its alternative text and when it was
     * uploaded; it opens its editor.
     */
    private function row(ImageResponse $image): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return new PageComponent('Shared:ResourceRow', [
            'title' => $image->alt,
            'url' => $this->urlGenerator->generate('cms_images_edit', ['id' => $image->id]),
            'meta' => $this->translator->trans('cms.images.list.uploaded_at', ['%date%' => $formatter->format($image->createdAt)]),
            'thumbnail' => $this->urlGenerator->generate('cms_images_file', ['id' => $image->id]),
            'status' => $this->imageState->label($image),
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
                'action' => $this->urlGenerator->generate('cms_images_create'),
                'method' => 'post',
                'csrfTokenId' => 'cms_images_create',
                'enctype' => 'multipart/form-data',
            ], [
                new PageComponent('Shared:ImageCropper', [
                    'name' => 'image',
                    'id' => 'image-file',
                    'label' => $this->translator->trans('cms.images.upload.image_label'),
                    'width' => Image::WIDTH,
                    'height' => Image::HEIGHT,
                    'quality' => self::QUALITY,
                    'required' => true,
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
                        'singleLine' => true,
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
