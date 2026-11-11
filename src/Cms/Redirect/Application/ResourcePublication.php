<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Publication;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;

/**
 * Publishes a resource and keeps the redirects true to it: the address it
 * takes answers it, so a redirect there goes; it is on the site again, so no
 * redirect knows it as the one that was at its address any longer; and the
 * address it leaves, when it moves, does what the editor says, by the
 * redirect of the id given when it has none yet.
 *
 * It holds the resource and both addresses while it works, and keeps all of
 * it or none. While held, it checks that publishing still does what the
 * editor confirmed: a redirect created meanwhile at the address it takes is
 * never deleted unseen.
 */
final readonly class ResourcePublication
{
    /**
     * @param string                  $redirectId the id of the redirect the address it leaves gets, if it has none
     * @param PreviousAddressDecision $previous   what the address it leaves does, when it moves
     * @param ?string                 $outlook    the fingerprint of what the editor was shown, empty when nothing; null to publish whatever it does, for a resource whose address never changes
     * @param \Closure(): void        $publish    puts the resource on the site
     */
    public function __construct(
        private string $redirectId,
        private Resource $resource,
        private PreviousAddressDecision $previous,
        private ?string $outlook,
        private \Closure $publish,
    ) {}

    public function __invoke(
        RedirectRepository $redirectRepository,
        SiteResources $siteResources,
        MutualExclusion $mutualExclusion,
        Transaction $transaction,
    ): void {
        $publication = Publication::now($this->resource, $redirectRepository, $siteResources);
        $next = $publication->next();
        $left = $publication->left();
        $target = null === $left ? null : $this->previous->target()?->target();

        $mutualExclusion->run($this->keys($next, $left, $target), fn () => $transaction->run(
            fn () => $this->publish($next, $left, $target, $redirectRepository, $siteResources),
        ));
    }

    /**
     * Puts it on the site, then the redirects to match, while it is held.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function publish(?Source $next, ?Source $left, ?Target $target, RedirectRepository $redirectRepository, SiteResources $siteResources): void
    {
        if (null !== $this->outlook) {
            Publication::now($this->resource, $redirectRepository, $siteResources)->assertConfirmed($this->outlook, $this->previous->isDecided());
        }

        ($this->publish)();

        // Its address answers it now, so nothing else answers there.
        $replaced = null === $next ? null : $redirectRepository->findBySource($next);
        $replaced?->delete($redirectRepository);

        foreach ($redirectRepository->findByOrigin($this->resource) as $redirect) {
            $redirect->forgetOrigin();
            $redirect->save($redirectRepository);
        }

        if (null !== $left && null !== $target) {
            $this->lead($left, $target, $redirectRepository, $siteResources);
        }
    }

    /**
     * What it holds: the resource, both addresses, and where the one it
     * leaves will lead, if anywhere.
     *
     * @return list<string>
     */
    private function keys(?Source $next, ?Source $left, ?Target $target): array
    {
        $targetResource = $target?->resource();

        return [
            $this->resource->key(),
            ...(null === $next ? [] : ['address:'.$next->value()]),
            ...(null === $left ? [] : ['address:'.$left->value()]),
            ...(null === $targetResource ? [] : [$targetResource->key()]),
        ];
    }

    /**
     * Leads the address it left where the editor says: the redirect there
     * changes, or one is created.
     */
    private function lead(Source $left, Target $target, RedirectRepository $redirectRepository, SiteResources $siteResources): void
    {
        $redirect = $redirectRepository->findBySource($left);

        // None is there, so none other can take it while it is held.
        if (null === $redirect) {
            $redirect = Redirect::create($this->redirectId, $left, $target, $siteResources);
        } else {
            $redirect->retarget($target, $siteResources);
        }

        $redirect->save($redirectRepository);
    }
}
