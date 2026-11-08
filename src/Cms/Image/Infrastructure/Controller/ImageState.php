<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where an image stands for the site, in the editor's language: a draft,
 * published, published with changes, or unpublished.
 */
final readonly class ImageState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(ImageResponse $image): string
    {
        return $this->translator->trans(\sprintf('cms.images.status.%s.label', $this->key($image)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(ImageResponse $image): string
    {
        return $this->translator->trans(\sprintf('cms.images.status.%s.description', $this->key($image)));
    }

    private function key(ImageResponse $image): string
    {
        return $image->changed ? 'changed' : $image->status;
    }
}
