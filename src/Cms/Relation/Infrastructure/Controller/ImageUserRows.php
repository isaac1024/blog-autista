<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageState;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The images that use a resource, as rows like those of the list of images.
 */
final readonly class ImageUserRows
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private ImageRepository $imageRepository,
        private ImageState $imageState,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $ids): array
    {
        return array_map($this->row(...), new ImagesFinder($ids)($this->imageRepository));
    }

    private function row(ImageResponse $image): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return new PageComponent('Shared:ResourceRow', [
            'title' => $image->alt,
            'url' => $this->urlGenerator->generate('cms_images_edit', ['id' => $image->id]),
            'meta' => \sprintf(
                '%s · %s',
                $this->translator->trans('cms.relations.types.image'),
                $this->translator->trans('cms.images.list.uploaded_at', ['%date%' => $formatter->format($image->createdAt)]),
            ),
            'thumbnail' => $this->urlGenerator->generate('cms_images_file', ['id' => $image->id]),
            'status' => $this->imageState->label($image),
        ]);
    }
}
