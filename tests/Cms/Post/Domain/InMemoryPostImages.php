<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Domain;

use Cms\Post\Domain\ImageId;
use Cms\Post\Domain\PostImages;

/**
 * The images of the library, as a test lays them out: by default the
 * opening image of PostMother, on the site.
 */
final readonly class InMemoryPostImages implements PostImages
{
    /**
     * @param list<string> $onSite ids of the images on the site
     * @param list<string> $drafts ids of the images the library has off the site
     */
    public function __construct(
        private array $onSite = [PostMother::OPENING_IMAGE],
        private array $drafts = [],
    ) {}

    #[\Override]
    public function exists(ImageId $id): bool
    {
        return $this->isOnSite($id) || \in_array($id->value(), $this->drafts, true);
    }

    #[\Override]
    public function isOnSite(ImageId $id): bool
    {
        return \in_array($id->value(), $this->onSite, true);
    }
}
