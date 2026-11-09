<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * One of the copies the site serves of a published image, all 16:9: the file
 * the CMS keeps at a path, at one of the widths. Its name and its address
 * are worked out here only, so the copies kept, the pages that point at them
 * and the addresses read back from a request always agree.
 *
 * A copy is named after the file of the CMS and its width, such as
 * 2026/11/<id>-800w.webp for 2026/11/<id>.webp, and served under /media/.
 */
final readonly class ImageCopy
{
    /**
     * Widths of the copies, with their heights, smallest first.
     */
    public const array SIZES = [400 => 225, 800 => 450, 1200 => 675];

    public const int LARGEST = 1200;

    private const string URL = '/media/';

    /**
     * What a path of the CMS looks like: directories and a name of lowercase
     * letters, digits, hyphens and underscores, never a step up.
     */
    private const string PATH = '(?:[a-z0-9_-]+/)*[a-z0-9_-]+';

    /**
     * @param string $path  where the CMS keeps the file, relative to its image storage, such as 2026/11/<id>.webp
     * @param int    $width one of the widths of the copies
     */
    public function __construct(
        private string $path,
        private int $width,
    ) {
        if (1 !== preg_match('#^'.self::PATH.'\.webp$#', $path)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not the path of an image.', $path));
        }

        if (!\array_key_exists($width, self::SIZES)) {
            throw new \InvalidArgumentException(\sprintf('No copy is %d pixels wide.', $width));
        }
    }

    /**
     * The copy an address of the site names, or null when it names none.
     */
    public static function fromUrl(string $url): ?self
    {
        if (1 !== preg_match('#^'.preg_quote(self::URL, '#').'('.self::PATH.')-([0-9]+)w\.webp$#', $url, $matches)) {
            return null;
        }

        $width = (int) $matches[2];

        return \array_key_exists($width, self::SIZES) ? new self($matches[1].'.webp', $width) : null;
    }

    /**
     * Whether an address of the site is where copies are served, whether it
     * names one or not.
     */
    public static function isMediaUrl(string $url): bool
    {
        return str_starts_with($url, self::URL);
    }

    /**
     * The path of the file of the CMS whose copies share the address, as
     * fileAddress() gives it, or null when it gives none.
     */
    public static function pathAtFileAddress(string $address): ?string
    {
        if (1 !== preg_match('#^'.preg_quote(self::URL, '#').'('.self::PATH.')$#', $address, $matches)) {
            return null;
        }

        return $matches[1].'.webp';
    }

    /**
     * The address every copy of its file shares, whatever its width, such as
     * /media/2026/11/<id>: what is said of one copy is said of them all.
     */
    public function fileAddress(): string
    {
        return self::URL.substr($this->path, 0, -\strlen('.webp'));
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return self::SIZES[$this->width];
    }

    /**
     * Its name, relative to where the site keeps the copies.
     */
    public function name(): string
    {
        return \sprintf('%s-%dw.webp', substr($this->path, 0, -\strlen('.webp')), $this->width);
    }

    /**
     * Its address on the site, relative to its root.
     */
    public function url(): string
    {
        return self::URL.$this->name();
    }
}
