<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Caption;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where an image is edited: its alternative text and its caption, next to the
 * image itself, so both are written looking at it. Its file never changes.
 */
final class ImageEditController extends AbstractController
{
    private const string FORM = 'image-form';

    /**
     * Where the CMS host serves the files of the images: the web server maps
     * it onto the image storage.
     */
    private const string MEDIA_URL = '/media/';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly TiptapRichText $tiptapRichText,
    ) {}

    #[Route('/images/{id}', name: 'cms_images_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ImageRepository $imageRepository): Response
    {
        try {
            $image = new ImageFinder($id)($imageRepository);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($image));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(ImageResponse $image): Page
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return CmsLayout::page(\sprintf(
            '%s · blog autista',
            $this->translator->trans('cms.images.edit.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.images.edit.title'),
                'subtitle' => $this->translator->trans('cms.images.edit.dates', [
                    '%uploaded%' => $formatter->format($image->createdAt),
                    '%updated%' => $formatter->format($image->updatedAt),
                ]),
            ])
            ->withMain('Shared:ImagePreview', [
                'src' => self::MEDIA_URL.$image->path,
                'alt' => $image->alt,
                'width' => Image::WIDTH,
                'height' => Image::HEIGHT,
            ])
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_images_update', ['id' => $image->id]),
                'method' => 'post',
                'id' => self::FORM,
            ], [
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
                        'value' => $image->alt,
                        'rows' => 2,
                        'recommendedMax' => Alt::MAX_LENGTH,
                        'attributes' => [
                            'required' => true,
                            'maxlength' => Alt::MAX_LENGTH,
                            'aria-describedby' => 'image-alt-hint image-alt-count',
                        ],
                    ]),
                ]),
                new PageComponent('Shared:RichTextEditor', [
                    'name' => 'caption',
                    'id' => 'image-caption-editor',
                    'label' => $this->translator->trans('cms.images.edit.caption_label'),
                    'hint' => $this->translator->trans('cms.images.edit.caption_hint'),
                    'placeholder' => $this->translator->trans('cms.images.edit.caption_placeholder'),
                    'value' => json_encode(
                        $this->tiptapRichText->toTiptap($image->caption ?? RichText::empty()->toArray()),
                        \JSON_THROW_ON_ERROR,
                    ),
                    'features' => Caption::features()->toArray(),
                    'singleParagraph' => true,
                    'maxLength' => Caption::MAX_LENGTH,
                ]),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', [
                        'label' => $this->translator->trans('cms.images.edit.submit'),
                    ]),
                ]),
            ]);
    }
}
