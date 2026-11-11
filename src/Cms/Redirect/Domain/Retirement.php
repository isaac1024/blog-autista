<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * What an editor decided of the addresses of a resource taken off the site:
 * where its own address leads, if anywhere, and what each redirect that
 * leads to it does, by the id of each.
 *
 * - Off the site, it can't be where its own address leads, nor where any
 *   redirect to it does: every choice is checked before anything changes.
 * - Every redirect that leads to it has a decision.
 */
final readonly class Retirement
{
    /**
     * @param ?Target                         $address  where its address leads once it is off the site; null to leave it not found
     * @param array<string, IncomingDecision> $incoming what each redirect that leads to it does, by its id
     *
     * @throws InvalidRedirect when a choice leads to the resource itself, or follows an address that leads nowhere
     */
    public function __construct(
        Resource $resource,
        private ?Target $address,
        private array $incoming,
    ) {
        $itself = Target::leadingTo($resource);

        if (null !== $address && $address->equals($itself)) {
            throw InvalidRedirect::toItself();
        }

        foreach ($incoming as $decision) {
            if ($decision->target($address)->equals($itself)) {
                throw InvalidRedirect::toItself();
            }
        }
    }

    public function address(): ?Target
    {
        return $this->address;
    }

    /**
     * What the editor decided of a redirect that leads to the resource.
     *
     * @throws InvalidRedirect when nothing was decided of it
     */
    public function decisionFor(Redirect $redirect): IncomingDecision
    {
        return $this->incoming[$redirect->id()->value()] ?? throw InvalidRedirect::undecided($redirect->source());
    }

    /**
     * The resources the redirects to it lead to of their own, besides where
     * its address leads.
     *
     * @return list<\Cms\Redirect\Domain\Resource>
     */
    public function incomingTargets(): array
    {
        $targets = [];

        foreach ($this->incoming as $decision) {
            $resource = $decision->resource();

            if (null !== $resource) {
                $targets[] = $resource;
            }
        }

        return $targets;
    }
}
