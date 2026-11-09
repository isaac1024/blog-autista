<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;

/**
 * Leads a redirect somewhere else, or says its address is gone. Its address
 * stays: changing it is deleting the redirect and creating another.
 */
final readonly class RedirectRetargeter
{
    public function __construct(
        private string $id,
        private TargetInput $target,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository, EventBus $eventBus, SiteResources $siteResources, MutualExclusion $mutualExclusion): void
    {
        $target = $this->target->target();
        $redirect = $redirectRepository->find(new RedirectId($this->id)) ?? throw RedirectNotFound::withId($this->id);

        $mutualExclusion->run(new RedirectKeys($redirect->source(), $target)(), function () use ($redirect, $target, $redirectRepository, $eventBus, $siteResources): void {
            $redirect->retarget($target, $siteResources);
            $redirect->save($redirectRepository, $eventBus);
        });
    }
}
