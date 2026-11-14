<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Outbound port that keeps the files of the avatars, out of the public
 * directory.
 */
interface AvatarStorage
{
    public function save(AvatarPath $path, AvatarFile $file): void;

    public function delete(AvatarPath $path): void;
}
