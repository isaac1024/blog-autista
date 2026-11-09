<?php

declare(strict_types=1);

namespace Web\Redirect\Application;

use Web\Redirect\Domain\CmsRedirectDeleted;
use Web\Redirect\Domain\RedirectId;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Takes off the site a redirect the CMS deleted. One already gone stays
 * gone, so the same event can be taken twice.
 */
final readonly class RedirectRemover
{
    public function __construct(private CmsRedirectDeleted $event) {}

    public function __invoke(RedirectRepository $redirectRepository): void
    {
        $redirectRepository->find(new RedirectId($this->event->aggregateId()))?->delete($redirectRepository);
    }
}
