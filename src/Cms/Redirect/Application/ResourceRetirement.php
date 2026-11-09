<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\SourceAvailability;
use Cms\Redirect\Domain\Target;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;

/**
 * Takes a resource off the site with what the editor decided of its
 * addresses: first every redirect that leads to it, each where the editor
 * says, since none may lead to a resource off the site; then the resource
 * itself; then its own address, which leads somewhere else, says it is gone,
 * or, with no choice made, is no longer found.
 *
 * It holds the resource, its address and every target while it works, and
 * keeps all of it or none: a resource is never off the site without the
 * redirects decided for it.
 */
final readonly class ResourceRetirement
{
    /**
     * @param ?TargetInput                    $address   where its address leads once it is off the site; null to leave it not found
     * @param array<string, IncomingDecision> $incoming  what to do with each redirect that leads to it, by its id
     * @param \Closure(): void                $unpublish takes the resource off the site
     */
    public function __construct(
        private Resource $resource,
        private ?TargetInput $address,
        private array $incoming,
        private \Closure $unpublish,
    ) {}

    public function __invoke(
        RedirectRepository $redirectRepository,
        EventBus $eventBus,
        SiteResources $siteResources,
        MutualExclusion $mutualExclusion,
        Transaction $transaction,
    ): void {
        $address = $this->address?->target();

        // Off the site, it can't be where its own addresses lead.
        if (null !== $address && $address->equals(Target::leadingTo($this->resource))) {
            throw InvalidRedirect::toItself();
        }

        $source = $siteResources->addressOf($this->resource);
        $keys = [$this->resource->key()];

        if (null !== $source) {
            $keys = [...$keys, ...new RedirectKeys($source, $address ?? Target::gone())()];
        }

        foreach ($this->incoming as $decision) {
            $keys = [...$keys, ...$decision->keys()];
        }

        $mutualExclusion->run($keys, fn () => $transaction->run(function () use ($address, $source, $redirectRepository, $eventBus, $siteResources): void {
            // Read again while held: no redirect can lead to it meanwhile.
            foreach ($redirectRepository->findTargeting($this->resource) as $redirect) {
                $decision = $this->incoming[$redirect->id()->value()] ?? throw InvalidRedirect::undecided($redirect->source());

                if ($decision->deletes()) {
                    $redirect->delete($redirectRepository, $eventBus);

                    continue;
                }

                $redirect->retarget($decision->target($address), $siteResources);
                $redirect->save($redirectRepository, $eventBus);
            }

            ($this->unpublish)();

            if (null === $address || null === $source) {
                return;
            }

            $redirect = Redirect::create(RedirectId::random()->value(), $source, $address, $siteResources);
            new SourceAvailability($redirect)($redirectRepository);
            $redirect->save($redirectRepository, $eventBus);
        }));
    }
}
