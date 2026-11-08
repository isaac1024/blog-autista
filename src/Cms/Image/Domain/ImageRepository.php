<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read images.
 */
interface ImageRepository
{
    public function save(Image $image): void;

    public function delete(Image $image): void;

    public function find(ImageId $id): ?Image;

    /**
     * @return list<Image>
     */
    public function matching(Criteria $criteria): array;
}
