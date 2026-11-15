<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Domain\ResourceImageFile;
use Shared\Domain\External\ImageShape;

/**
 * A wide WebP on disk, as the fetcher hands one over for a video, and
 * removes it once the test is done with it.
 */
trait ExternalResourceImageFile
{
    /** @var list<string> */
    private array $imageFiles = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->imageFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function landscapeImageFile(): ResourceImageFile
    {
        $path = tempnam(sys_get_temp_dir(), 'external-image-');
        $image = imagecreatetruecolor(1200, 675);
        self::assertNotFalse($path);
        self::assertNotFalse($image);
        imagewebp($image, $path);
        $this->imageFiles[] = $path;

        return new ResourceImageFile($path, ImageShape::Landscape);
    }
}
