<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Storage;

use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImageFile;
use Shared\Domain\External\ImageShape;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteUnreachable;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that downloads the image of a resource over HTTP, takes
 * it only when it is a JPEG, PNG, WebP or GIF image no heavier than it may
 * be, and crops and encodes it as WebP, at the size of its shape, in a file
 * of its own.
 */
#[AsAlias(ResourceImageFetcher::class)]
final readonly class HttpResourceImageFetcher implements ResourceImageFetcher
{
    public const int MAX_BYTES = 5_000_000;

    private const array TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * WebP quality of the file kept, where the loss doesn't show.
     */
    private const int QUALITY = 85;

    public function __construct(
        private RemoteHttp $http,
        private ImageResizer $imageResizer,
        private LocalFilesystem $filesystem,
    ) {}

    #[\Override]
    public function fetch(string $url, ImageShape $shape): ?ResourceImageFile
    {
        try {
            $response = $this->http->get($url, self::MAX_BYTES, 'image/webp, image/png, image/jpeg, image/gif;q=0.8');
        } catch (RemoteUnreachable) {
            return null;
        }

        if (!$response->isSuccessful() || $response->truncated) {
            return null;
        }

        // What the file is, told by its content, whatever the server says.
        if (!\in_array(new \finfo(\FILEINFO_MIME_TYPE)->buffer($response->body), self::TYPES, true)) {
            return null;
        }

        $source = $this->filesystem->temporary('external-image-', $response->body);
        $target = $this->filesystem->temporary('external-webp-', '');

        try {
            $this->imageResizer->cover($source, $target, $shape->width(), $shape->height(), self::QUALITY);

            return new ResourceImageFile($target, $shape);
        } catch (\Throwable) {
            // GD can't read it, though it looks like an image.
            $this->filesystem->remove($target);

            return null;
        } finally {
            $this->filesystem->remove($source);
        }
    }
}
