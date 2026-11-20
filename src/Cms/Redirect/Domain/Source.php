<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\ImageCopy;
use Shared\Domain\PostPath;
use Shared\Domain\SitePath;

/**
 * The address of the site a redirect answers, as SitePath writes it.
 *
 * The copies of an image share one: the address of its file, such as
 * /media/2026/11/<id>, so whatever width is asked for, the same width of the
 * target is given. Any other address under /media/ names nothing to redirect.
 *
 * The addresses of a post share one too: without its section, such as
 * /2026/11/07/<slug>.html, which the site looks up whatever section an
 * address names.
 */
#[ORM\Embeddable]
final readonly class Source
{
    private function __construct(
        #[ORM\Column(name: 'source', length: SitePath::MAX_LENGTH)]
        private string $value,
    ) {}

    /**
     * @throws InvalidRedirect when the address is under /media/ and names no copy of an image
     */
    public static function fromUrl(string $url, string $host): self
    {
        $path = SitePath::fromUrl($url, $host)->value();

        if (!ImageCopy::isMediaUrl($path)) {
            return new self(PostPath::withoutSection($path) ?? $path);
        }

        $copy = ImageCopy::fromUrl($path) ?? throw InvalidRedirect::notAnImageCopy($url);

        return new self($copy->fileAddress());
    }

    /**
     * The address of a page, as the site serves it.
     */
    public static function fromPath(string $path): self
    {
        $path = SitePath::fromRequest($path)?->value() ?? $path;

        return new self(PostPath::withoutSection($path) ?? $path);
    }

    /**
     * The address every copy of an image shares, by the path of its file.
     */
    public static function ofImage(string $path): self
    {
        return new self(new ImageCopy($path, ImageCopy::LARGEST)->fileAddress());
    }

    /**
     * An address as it was written here, read back from where it was kept.
     */
    public static function fromStored(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Where the CMS keeps the file of the image whose copies it is the
     * address of, or null when it is the address of no image.
     */
    public function imagePath(): ?string
    {
        return ImageCopy::pathAtFileAddress($this->value);
    }

    public function isImage(): bool
    {
        return null !== $this->imagePath();
    }

    /**
     * The address to show an editor, as it reads: decoded, such as /guía.html
     * for /gu%c3%ada.html, and the largest copy, for an image. Written back,
     * it is the same address.
     */
    public function shown(): string
    {
        $imagePath = $this->imagePath();

        return rawurldecode(null === $imagePath ? $this->value : new ImageCopy($imagePath, ImageCopy::LARGEST)->url());
    }
}
