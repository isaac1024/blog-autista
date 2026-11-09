<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Shared\Domain\MutualExclusion;

/**
 * Leads a redirect somewhere else, or says its address is gone. Its address
 * stays: changing it is deleting the redirect and creating another.
 *
 * It holds its address, as every change to a redirect does, and the target,
 * and reads the redirect only then, so it changes what the last change left.
 */
final readonly class RedirectRetargeter
{
    public function __construct(
        private string $id,
        private TargetInput $target,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository, SiteResources $siteResources, MutualExclusion $mutualExclusion): void
    {
        $target = $this->target->target();
        $id = new RedirectId($this->id);
        $source = $redirectRepository->sourceOf($id) ?? throw RedirectNotFound::withId($this->id);

        $mutualExclusion->run(new RedirectKeys($source, $target)(), function () use ($id, $target, $redirectRepository, $siteResources): void {
            $redirect = $redirectRepository->find($id) ?? throw RedirectNotFound::withId($this->id);
            $redirect->retarget($target, $siteResources);
            $redirect->save($redirectRepository);
        });
    }
}
