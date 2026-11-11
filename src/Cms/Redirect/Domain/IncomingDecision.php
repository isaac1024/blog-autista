<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * What an editor decides of a redirect that leads to a resource being taken
 * off the site: it leads where the address of that resource will, somewhere
 * else, says its address is gone, or is deleted.
 */
final readonly class IncomingDecision
{
    /**
     * @param ?Target $target where it leads of its own; null when it follows the address of the resource, or is deleted
     */
    private function __construct(
        private ?Target $target,
        private bool $followsAddress,
    ) {}

    public static function sameAsAddress(): self
    {
        return new self(null, true);
    }

    public static function delete(): self
    {
        return new self(null, false);
    }

    /**
     * Leads somewhere else, or says the address is gone.
     */
    public static function leadingTo(Target $target): self
    {
        return new self($target, false);
    }

    public function deletes(): bool
    {
        return null === $this->target && !$this->followsAddress;
    }

    /**
     * Where it leads, given where the address of the resource will.
     *
     * @param ?Target $address where the address of the resource will lead, null when nowhere is chosen
     *
     * @throws InvalidRedirect when it follows an address that leads nowhere
     */
    public function target(?Target $address): Target
    {
        if (null !== $this->target) {
            return $this->target;
        }

        if (!$this->followsAddress) {
            // Deleted, it leads nowhere.
            return Target::gone();
        }

        return null === $address || null === $address->resource() ? throw InvalidRedirect::noNewTarget() : $address;
    }

    /**
     * The resource it leads to of its own, besides where the address of the
     * resource leads: none when it follows that address, or is deleted.
     */
    public function resource(): ?Resource
    {
        return $this->target?->resource();
    }
}
