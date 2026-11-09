<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

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
 * it or none.
 */
final readonly class ResourcePublication
{
    /**
     * @param ?TargetInput     $previous where the address it leaves leads, when it moves; null leaves that address as it is
     * @param \Closure(): void $publish  puts the resource on the site
     */
    public function __construct(
        private Resource $resource,
        private ?TargetInput $previous,
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
        $target = null === $left ? null : $this->previous?->target();

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
