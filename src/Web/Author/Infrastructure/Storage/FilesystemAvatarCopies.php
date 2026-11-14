<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Storage;

use Shared\Domain\AvatarCopy;
use Shared\Infrastructure\Filesystem\LocalFilesystem;
use Shared\Infrastructure\Image\ImageResizer;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Web\Author\Domain\AvatarCopies;

/**
 * Outbound adapter that makes the copies of the avatars in the public
 * directory of the site: the largest is the file of the CMS as it is, and
 * the smaller ones are resized from it.
 */
#[AsAlias(AvatarCopies::class)]
final readonly class FilesystemAvatarCopies implements AvatarCopies
{
    /**
     * WebP quality of the copies made, as the browser encodes the file of the CMS.
     */
    private const int QUALITY = 85;

    public function __construct(
        private LocalFilesystem $filesystem,
        private ImageResizer $imageResizer,
        #[Autowire(param: 'web.author.originals_directory')]
        private string $originalsDirectory,
        #[Autowire(param: 'web.author.public_directory')]
        private string $publicDirectory,
    ) {}

    #[\Override]
    public function publish(string $path, string $name): void
    {
        $source = \sprintf('%s/%s', $this->originalsDirectory, $path);

        foreach (AvatarCopy::SIZES as $size) {
            $copy = new AvatarCopy($name, $size);
            $target = $this->kept($copy);

            if ($this->filesystem->exists($target)) {
                continue;
            }

            if (AvatarCopy::LARGEST === $size) {
                $this->filesystem->copy($source, $target, 0o644);

                continue;
            }

            $this->imageResizer->resize($source, $target, $size, $size, self::QUALITY);
        }
    }

    #[\Override]
    public function remove(string $name): void
    {
        foreach (AvatarCopy::SIZES as $size) {
            $this->filesystem->remove($this->kept(new AvatarCopy($name, $size)));
        }
    }

    private function kept(AvatarCopy $copy): string
    {
        return \sprintf('%s/%s', $this->publicDirectory, $copy->file());
    }
}
