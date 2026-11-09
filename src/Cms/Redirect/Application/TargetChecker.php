<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;

/**
 * Tells whether any redirect leads to a resource, which then stays on the
 * site.
 */
final readonly class TargetChecker
{
    public function __construct(
        private ResourceType $type,
        private string $id,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository): bool
    {
        return $redirectRepository->isTargeted(new Resource($this->type, $this->id));
    }
}
