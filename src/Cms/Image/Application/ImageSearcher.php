<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\AltFilter;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists the images of the library, newest first, a page at a time, optionally
 * only those whose alternative text has every word of a text.
 */
final readonly class ImageSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $alt,
        private int $page,
    ) {}

    /**
     * @return list<ImageResponse>
     */
    public function __invoke(ImageRepository $imageRepository): array
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $alt = trim($this->alt);

        // Image ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: '' === $alt ? [] : [AltFilter::matches($alt)],
            order: new Order('id', OrderDirection::Descending),
            limit: self::PAGE_SIZE,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        return array_map(ImageResponse::fromImage(...), $imageRepository->matching($criteria));
    }
}
