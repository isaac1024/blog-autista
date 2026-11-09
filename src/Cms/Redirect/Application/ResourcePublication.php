<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\SourceAvailability;
use Cms\Redirect\Domain\Target;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;

/**
 * Publishes a resource and keeps the redirects true to it: the address it
 * takes answers it, so a redirect there goes; it is on the site again, so no
 * redirect knows it as the one that was at its address any longer; and the
 * address it leaves, when it moves, does what the editor says.
 *
 * It holds the resource and both addresses while it works, and keeps all of
 * it or none. While held, it checks that publishing still does what the
 * editor confirmed, by the fingerprint of what was shown: a redirect created
 * meanwhile at the address it takes is never deleted unseen.
 */
final readonly class ResourcePublication
{
    /**
     * @param PreviousAddressDecision $previous what the address it leaves does, when it moves
     * @param ?string                 $outlook  the fingerprint of what the editor was shown, empty when nothing; null to publish whatever it does, for a resource whose address never changes
     * @param \Closure(): void        $publish  puts the resource on the site
     */
    public function __construct(
        private Resource $resource,
        private PreviousAddressDecision $previous,
        private ?string $outlook,
        private \Closure $publish,
    ) {}

    /**
     * PHPMD measures it over the limits only when it analyses the whole
     * source tree; on its own, it is within them.
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    public function __invoke(
        RedirectRepository $redirectRepository,
        EventBus $eventBus,
        SiteResources $siteResources,
        MutualExclusion $mutualExclusion,
        Transaction $transaction,
    ): void {
        $next = $siteResources->nextAddressOf($this->resource);
        $left = self::left($siteResources->addressOf($this->resource), $next);
        $target = null === $left ? null : $this->previous->target()?->target();

        $mutualExclusion->run($this->keys($next, $left, $target), fn () => $transaction->run(
            fn () => $this->publish($next, $left, $target, $redirectRepository, $eventBus, $siteResources),
        ));
    }

    /**
     * Puts it on the site, then the redirects to match, while it is held.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function publish(?Source $next, ?Source $left, ?Target $target, RedirectRepository $redirectRepository, EventBus $eventBus, SiteResources $siteResources): void
    {
        $this->assertConfirmed($redirectRepository, $siteResources);
        ($this->publish)();

        // Its address answers it now, so nothing else answers there.
        $replaced = null === $next ? null : $redirectRepository->findBySource($next);
        $replaced?->delete($redirectRepository, $eventBus);

        foreach ($redirectRepository->findByOrigin($this->resource) as $redirect) {
            $redirect->forgetOrigin();
            $redirect->save($redirectRepository, $eventBus);
        }

        if (null !== $left && null !== $target) {
            self::lead($left, $target, $redirectRepository, $eventBus, $siteResources);
        }
    }

    /**
     * Whether publishing, as it would be now, is what the editor was shown,
     * and the address it leaves, if any, was decided.
     */
    private function assertConfirmed(RedirectRepository $redirectRepository, SiteResources $siteResources): void
    {
        if (null === $this->outlook) {
            return;
        }

        $outlook = new PublicationOutlookFinder($this->resource)($redirectRepository, $siteResources);

        if ($outlook->fingerprint() !== $this->outlook) {
            throw InvalidRedirect::outlookChanged();
        }

        if (null !== $outlook->previous && !$this->previous->isDecided()) {
            throw InvalidRedirect::undecidedPrevious();
        }
    }

    /**
     * The address it leaves: the one it has, or last had, when it moves to
     * another.
     */
    private static function left(?Source $current, ?Source $next): ?Source
    {
        return null === $current || null === $next || $current->equals($next) ? null : $current;
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
    private static function lead(Source $left, Target $target, RedirectRepository $redirectRepository, EventBus $eventBus, SiteResources $siteResources): void
    {
        $redirect = $redirectRepository->findBySource($left);

        if (null === $redirect) {
            $redirect = Redirect::create(RedirectId::random()->value(), $left, $target, $siteResources);
            new SourceAvailability($redirect)($redirectRepository);
        } else {
            $redirect->retarget($target, $siteResources);
        }

        $redirect->save($redirectRepository, $eventBus);
    }
}
