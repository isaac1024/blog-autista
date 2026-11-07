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

    /**
     * @return list<Image>
     */
    public function matching(Criteria $criteria): array;
}
