<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\SourceAvailability;
use Shared\Domain\MutualExclusion;

/**
 * Creates a redirect from an absolute address of the site, at its host, to a
 * resource on it, or saying the address is gone.
 *
 * It holds the address and the target while it checks and saves, so neither
 * another redirect there nor taking the target off the site slips between.
 */
final readonly class RedirectCreator
{
    public function __construct(
        private string $id,
        private string $url,
        private TargetInput $target,
        private string $siteHost,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository, SiteResources $siteResources, MutualExclusion $mutualExclusion): void
    {
        $source = Source::fromUrl($this->url, $this->siteHost);
        $target = $this->target->target();

        $mutualExclusion->run(new RedirectKeys($source, $target)(), function () use ($source, $target, $redirectRepository, $siteResources): void {
            $redirect = Redirect::create($this->id, $source, $target, $siteResources);
            new SourceAvailability($redirect)($redirectRepository);
            $redirect->save($redirectRepository);
        });
    }
}
