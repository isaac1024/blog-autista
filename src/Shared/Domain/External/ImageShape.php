<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * The shape of the image of an external resource, the one its facade or its
 * card shows: wide for most, upright for an upright video, square for the
 * cover of an audio. The CMS keeps it at its largest size, and the site
 * serves copies of it at the widths it has.
 */
enum ImageShape: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';
    case Square = 'square';

    /**
     * The width of the file the CMS keeps, the largest copy the site serves.
     */
    public function width(): int
    {
        return match ($this) {
            self::Landscape => 1200,
            self::Portrait => 540,
            self::Square => 640,
        };
    }

    public function height(): int
    {
        return $this->heightAt($this->width());
    }

    /**
     * The widths of the copies the site serves, smallest first, the largest
     * being the file of the CMS as it is.
     *
     * @return non-empty-list<int>
     */
    public function widths(): array
    {
        return match ($this) {
            self::Landscape => [400, 800, 1200],
            self::Portrait => [270, 540],
            self::Square => [160, 320, 640],
        };
    }

    /**
     * The height of a copy of the width.
     */
    public function heightAt(int $width): int
    {
        return match ($this) {
            self::Landscape => intdiv($width * 9, 16),
            self::Portrait => intdiv($width * 16, 9),
            self::Square => $width,
        };
    }
}
