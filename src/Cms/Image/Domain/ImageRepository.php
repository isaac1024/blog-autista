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
     * The images among those given; an id of no image is left out.
     *
     * @param list<ImageId> $ids
     *
     * @return list<Image>
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<Image>
     */
    public function matching(Criteria $criteria): array;
}
