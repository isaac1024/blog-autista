<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Storage;

use Cms\External\Domain\ResourceImageFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\External\ImageShape;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The image a resource tells, downloaded and made into the file the CMS
 * keeps: cropped to its shape and encoded as WebP, whatever it came as; and
 * nothing at all of what isn't an image the CMS takes.
 */
final class HttpResourceImageFetcherTest extends KernelTestCase
{
    private const string URL = 'https://images.example.org/portada';

    #[\Override]
    protected function tearDown(): void
    {
        RecordedSites::forget();
        parent::tearDown();
    }

    #[DataProvider('images')]
    public function testItMakesAWebpOfTheSizeOfTheShape(string $type, ImageShape $shape): void
    {
        RecordedSites::answer(self::URL, self::image($type), 200, ['content-type' => 'application/octet-stream']);

        $file = self::getContainer()->get(ResourceImageFetcher::class)->fetch(self::URL, $shape);

        self::assertNotNull($file);
        $size = getimagesize($file->path());
        unlink($file->path());
        self::assertNotFalse($size);
        self::assertSame(['image/webp', $shape->width(), $shape->height()], [$size['mime'], $size[0], $size[1]]);
    }

    /**
     * @return iterable<string, array{string, ImageShape}>
     */
    public static function images(): iterable
    {
        yield 'a JPEG, wide' => ['jpeg', ImageShape::Landscape];
        yield 'a PNG, upright' => ['png', ImageShape::Portrait];
        yield 'a GIF, square' => ['gif', ImageShape::Square];
        yield 'a WebP' => ['webp', ImageShape::Landscape];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('refused')]
    public function testItMakesNothingOfWhatIsNoImageItTakes(string $body, int $status, array $headers): void
    {
        RecordedSites::answer(self::URL, $body, $status, $headers);

        self::assertNull(self::getContainer()->get(ResourceImageFetcher::class)->fetch(self::URL, ImageShape::Landscape));
    }

    /**
     * @return iterable<string, array{string, int, array<string, string>}>
     */
    public static function refused(): iterable
    {
        yield 'an image no longer there' => [self::image('png'), 404, ['content-type' => 'image/png']];
        yield 'a vector image' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 200, ['content-type' => 'image/svg+xml']];
        yield 'a page called an image' => ['<html></html>', 200, ['content-type' => 'image/png']];
        yield 'a PNG broken half way' => [substr(self::image('png'), 0, 60), 200, ['content-type' => 'image/png']];
        yield 'an image too heavy' => [str_repeat('a', 5_000_001), 200, ['content-type' => 'image/png']];
    }

    /**
     * A PNG that tells it is 20000 × 20000 in a few KB: decoding it would
     * take GD more memory than PHP has, and end it with a fatal error.
     */
    public function testItMakesNothingOfAnImageTooLargeWithoutDecodingIt(): void
    {
        RecordedSites::answer(self::URL, self::pngOf(20_000, 20_000), 200, ['content-type' => 'image/png']);

        self::assertNull(self::getContainer()->get(ResourceImageFetcher::class)->fetch(self::URL, ImageShape::Landscape));
    }

    public function testItTakesAnImageAsLargeAsAPhoto(): void
    {
        RecordedSites::answer(self::URL, self::image('jpeg', 1600, 900), 200, ['content-type' => 'image/jpeg']);

        $file = self::getContainer()->get(ResourceImageFetcher::class)->fetch(self::URL, ImageShape::Landscape);

        self::assertNotNull($file);
        unlink($file->path());
    }

    public function testItMakesNothingOfAnImageThatCannotBeReached(): void
    {
        RecordedSites::failToReach(self::URL);

        self::assertNull(self::getContainer()->get(ResourceImageFetcher::class)->fetch(self::URL, ImageShape::Landscape));
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private static function image(string $type, int $width = 320, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        ob_start();
        match ($type) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            default => imagewebp($image),
        };

        return (string) ob_get_clean();
    }

    /**
     * A PNG of the width and the height its header tells, whose data, a few
     * KB of compressed blank rows, holds a small part of them.
     */
    private static function pngOf(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));

        // 8 bits per channel, truecolor with alpha.
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
        $rows = (string) gzcompress(str_repeat("\0", 16 * (1 + 4 * $width)));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', $header).$chunk('IDAT', $rows).$chunk('IEND', '');
    }
}
