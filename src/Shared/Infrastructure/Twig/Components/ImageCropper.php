<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Picks an image and the part of it to keep, at a fixed aspect ratio, and
 * sends that part at the output size, encoded as WebP, through a file field.
 *
 * The browser does the work, run by the image-cropper controller: the image
 * chosen never leaves it, only the crop does.
 */
#[AsTwigComponent]
final class ImageCropper
{
    /**
     * Name of the field that carries the crop.
     */
    public string $name = '';

    public string $id = '';

    public string $label = '';

    /**
     * Size of the crop sent, in pixels; it also sets the aspect ratio and the
     * smallest image accepted, since images are never enlarged.
     */
    public int $width = 0;

    public int $height = 0;

    /**
     * WebP quality, between 0 and 1.
     */
    public float $quality = 0;

    /**
     * Whether the form needs an image to be sent, or may go without one, as
     * when it replaces one that is kept already.
     */
    public bool $required = false;

    /**
     * Its props are declared here, as for every component, so it takes as
     * many parameters as it has props.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function mount(string $name, string $id, string $label, int $width, int $height, float $quality, bool $required): void
    {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->width = $width;
        $this->height = $height;
        $this->quality = $quality;
        $this->required = $required;
    }
}
