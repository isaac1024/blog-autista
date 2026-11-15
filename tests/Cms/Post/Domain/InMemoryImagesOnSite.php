<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Domain;

use Cms\Post\Domain\ImageId;
use Cms\Post\Domain\ImagesOnSite;

/**
 * The images on the site, as a test lays them out: by default the opening
 * image of PostMother.
 */
final readonly class InMemoryImagesOnSite implements ImagesOnSite
{
    /**
     * @param list<string> $onSite ids of the images on the site
     */
    public function __construct(private array $onSite = [PostMother::OPENING_IMAGE]) {}

    #[\Override]
    public function isOnSite(ImageId $id): bool
    {
        return \in_array($id->value(), $this->onSite, true);
    }
}
