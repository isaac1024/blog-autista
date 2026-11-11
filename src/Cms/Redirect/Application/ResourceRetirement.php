<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\IncomingDecision;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\Retirement;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\SourceAvailability;
use Cms\Redirect\Domain\Target;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;

/**
 * Takes a resource off the site with what the editor decided of its
 * addresses: first every redirect that leads to it, each where the editor
 * says, since none may lead to a resource off the site; then the resource
 * itself; then its own address, which leads somewhere else by the redirect
 * of the id given, says it is gone, or, with no choice made, is no longer
 * found.
 *
 * It holds the resource, its address and every target while it works, and
 * keeps all of it or none: a resource is never off the site without the
 * redirects decided for it.
 */
final readonly class ResourceRetirement
{
    /**
     * @param string                          $redirectId the id of the redirect its address gets, if it leads anywhere
     * @param ?TargetInput                    $address    where its address leads once it is off the site; null to leave it not found
     * @param array<string, IncomingDecision> $incoming   what to do with each redirect that leads to it, by its id
     * @param \Closure(): void                $unpublish  takes the resource off the site
     */
    public function __construct(
        private string $redirectId,
        private Resource $resource,
        private ?TargetInput $address,
        private array $incoming,
        private \Closure $unpublish,
    ) {}

    public function __invoke(
        RedirectRepository $redirectRepository,
        SiteResources $siteResources,
        MutualExclusion $mutualExclusion,
        Transaction $transaction,
    ): void {
        $retirement = new Retirement($this->resource, $this->address?->target(), $this->incoming);
        $source = $siteResources->addressOf($this->resource);

        $mutualExclusion->run(
            $this->keys($retirement, $source, $redirectRepository),
            fn () => $transaction->run(fn () => $this->retire($retirement, $source, $redirectRepository, $siteResources)),
        );
    }

    /**
     * What it holds: the resource, its address and where it will lead, and
     * the address of every redirect to it, as every change to a redirect
     * holds its address, with where each will lead. A redirect to it created
     * meanwhile isn't held, and has nothing decided, so it is refused.
     *
     * @return list<string>
     */
    private function keys(Retirement $retirement, ?Source $source, RedirectRepository $redirectRepository): array
    {
        return [
            $this->resource->key(),
            ...(null === $source ? [] : new RedirectKeys($source, $retirement->address() ?? Target::gone())()),
            ...array_map(static fn (Source $incoming): string => 'address:'.$incoming->value(), $redirectRepository->sourcesTargeting($this->resource)),
            ...array_map(static fn (Resource $target): string => $target->key(), $retirement->incomingTargets()),
        ];
    }

    /**
     * Decides every redirect to it, takes it off the site, then leads its
     * address, while all of it is held.
     */
    private function retire(Retirement $retirement, ?Source $source, RedirectRepository $redirectRepository, SiteResources $siteResources): void
    {
        // Read again while held: no redirect can lead to it meanwhile.
        foreach ($redirectRepository->findTargeting($this->resource) as $redirect) {
            $redirect->followRetirement($retirement, $redirectRepository, $siteResources);
        }

        ($this->unpublish)();

        $address = $retirement->address();

        if (null === $address || null === $source) {
            return;
        }

        $redirect = Redirect::create($this->redirectId, $source, $address, $siteResources);
        new SourceAvailability($redirect)($redirectRepository);
        $redirect->save($redirectRepository);
    }
}
