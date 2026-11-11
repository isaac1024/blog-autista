<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where an image is taken off the site: the image it is, a way
 * back to its editor, and the form that asks what becomes of its addresses.
 */
final readonly class ImageUnpublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RetirementForm $retirementForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(ImageResponse $image, array $saved): Page
    {
        $title = $this->translator->trans('cms.images.unpublish.page_title', ['%alt%' => $image->alt]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_images_edit', ['id' => $image->id]),
                    'label' => $this->translator->trans('cms.images.unpublish.back'),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::image($image->id),
            $this->urlGenerator->generate('cms_images_unpublish', ['id' => $image->id]),
            'cms_images_unpublish',
            $this->translator->trans('cms.images.unpublish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
