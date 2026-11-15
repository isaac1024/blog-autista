<?php

declare(strict_types=1);

namespace Shared\Domain;

use Shared\Domain\External\ImageShape;

/**
 * A copy of the image of an external resource, one of the widths the site
 * serves for its shape. The copies of an image share the name of the file
 * the CMS keeps, unique to it, so a new image never reuses the name of
 * copies browsers keep for good.
 */
final readonly class ExternalImageCopy
{
    private const string URL = '/media/externos/';

    private string $name;

    /**
     * @param string $path  where the CMS keeps the file, relative to its storage, such as <id>.webp
     * @param int    $width one of the widths of the shape
     */
    public function __construct(
        string $path,
        private ImageShape $shape,
        private int $width,
    ) {
        if (1 !== preg_match('/^([0-9a-f-]{36})\.webp$/', $path, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the path of the image of an external resource.', $path));
        }

        if (!\in_array($width, $shape->widths(), true)) {
            throw new \InvalidArgumentException(\sprintf('No copy of a %s image is %d pixels wide.', $shape->value, $width));
        }

        $this->name = $matches[1];
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->shape->heightAt($this->width);
    }

    /**
     * Its file name, relative to where the site keeps the copies.
     */
    public function file(): string
    {
        return \sprintf('%s-%dw.webp', $this->name, $this->width);
    }

    /**
     * Its address on the site, relative to its root.
     */
    public function url(): string
    {
        return self::URL.$this->file();
    }
}
