<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Caption;
use Cms\Image\Domain\Image;
use Cms\Redirect\Infrastructure\Controller\LastAddressPanel;
use Cms\Shared\Infrastructure\Ui\LinkTargets;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of an image: its alternative text and its caption,
 * next to the image itself, so both are written looking at it, with what is
 * done to it apart from editing, each offered only when the image allows it.
 * Its file never changes.
 */
final readonly class ImageEditor
{
    private const string FORM = 'image-form';

    private const string PUBLISH_FORM = 'image-publish-form';

    private const string DELETE_IMAGE_MODAL = 'delete-image';

    private const string DISCARD_CHANGES_MODAL = 'discard-changes';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private TiptapRichText $tiptapRichText,
        private ImageState $imageState,
        private LastAddressPanel $lastAddressPanel,
        private LinkTargets $linkTargets,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(ImageResponse $image): Page
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->lastAddressPanel->onImage(CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans('cms.images.edit.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.images.edit.title'),
                'subtitle' => $this->translator->trans('cms.images.edit.dates', [
                    '%uploaded%' => $formatter->format($image->createdAt),
                    '%updated%' => $formatter->format($image->updatedAt),
                ]),
                'status' => $this->imageState->label($image),
            ], $this->imageActions($image)), $image->id)
            ->withMain('Shared:ImagePreview', [
                'src' => $this->urlGenerator->generate('cms_images_file', ['id' => $image->id]),
                'alt' => $image->alt,
                'width' => Image::WIDTH,
                'height' => Image::HEIGHT,
            ])
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_images_update', ['id' => $image->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_images_update',
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
                        'singleLine' => true,
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
                    'linkTargets' => ($this->linkTargets)(null),
                ]),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->imageState->description($image),
                ], $this->formActions($image)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_images_publish', ['id' => $image->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_images_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * What is done to the image apart from editing it: seeing what uses it
     * and, each confirmed first and offered only when the image allows it,
     * discarding its unpublished changes, taking it off the site, or deleting
     * it.
     *
     * @return list<PageComponent>
     */
    private function imageActions(ImageResponse $image): array
    {
        // What uses the image, on a page of its own.
        $actions = [
            new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_relations', ['type' => 'images', 'id' => $image->id]),
                'label' => $this->translator->trans('cms.images.edit.relations'),
            ]),
        ];

        if ($image->changed) {
            $actions[] = $this->confirmModal($image, self::DISCARD_CHANGES_MODAL, 'discard', 'cms_images_discard', 'secondary');
        }

        if ($image->canBeUnpublished) {
            // Taking it off the site asks what becomes of its addresses, on a
            // page of its own.
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_images_unpublish_form', ['id' => $image->id]),
                'label' => $this->translator->trans('cms.images.unpublish.trigger'),
            ]);
        }

        if ($image->canBeDeleted) {
            $actions[] = $this->confirmModal($image, self::DELETE_IMAGE_MODAL, 'delete', 'cms_images_delete', 'danger');
        }

        return $actions;
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published image offers it again only once it has changes to
     * publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(ImageResponse $image): array
    {
        $publishable = $image->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.images.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('published' === $image->status ? 'cms.images.edit.republish' : 'cms.images.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.images.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * A modal that confirms an action on the image, with its texts under
     * cms.images.{action}.
     */
    private function confirmModal(ImageResponse $image, string $id, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $id,
            'trigger' => $this->translator->trans(\sprintf('cms.images.%s.trigger', $action)),
            'title' => $this->translator->trans(\sprintf('cms.images.%s.title', $action)),
            'description' => $this->translator->trans(\sprintf('cms.images.%s.description', $action), ['%alt%' => $image->alt]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate($route, ['id' => $image->id]),
                'method' => 'post',
                'csrfTokenId' => $route,
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans(\sprintf('cms.images.%s.submit', $action)),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }
}
