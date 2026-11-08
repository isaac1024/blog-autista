<?php

declare(strict_types=1);

namespace Web\Image\Domain;

/**
 * Outbound port used to persist and read the images on the site.
 */
interface ImageRepository
{
    public function save(Image $image): void;

    public function delete(Image $image): void;

    public function find(ImageId $id): ?Image;

    /**
     * The images on the site among those given; any other is left out.
     *
     * @param list<ImageId> $ids
     *
     * @return list<Image>
     */
    public function findByIds(array $ids): array;
}
