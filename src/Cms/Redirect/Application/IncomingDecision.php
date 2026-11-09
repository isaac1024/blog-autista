<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Target;

/**
 * What an editor decides of a redirect that leads to a resource being taken
 * off the site: it leads where the address of that resource will, somewhere
 * else, says its address is gone, or is deleted.
 */
final readonly class IncomingDecision
{
    private function __construct(
        private bool $sameAsAddress,
        private bool $delete,
        private ?TargetInput $target,
    ) {}

    public static function sameAsAddress(): self
    {
        return new self(true, false, null);
    }

    public static function delete(): self
    {
        return new self(false, true, null);
    }

    /**
     * Leads somewhere else, or says the address is gone.
     */
    public static function leadingTo(TargetInput $target): self
    {
        return new self(false, false, $target);
    }

    public function deletes(): bool
    {
        return $this->delete;
    }

    /**
     * Where it leads, given where the address of the resource will.
     *
     * @param ?Target $address where the address of the resource will lead, null when nowhere is chosen
     */
    public function target(?Target $address): Target
    {
        if ($this->sameAsAddress) {
            return null === $address || null === $address->resource() ? throw InvalidRedirect::noNewTarget() : $address;
        }

        return $this->target?->target() ?? Target::gone();
    }

    /**
     * What the change relies on besides the redirect itself, so it can be
     * held: the resource it will lead to, if any.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $resource = $this->sameAsAddress || $this->delete ? null : $this->target?->target()->resource();

        return null === $resource ? [] : [$resource->key()];
    }
}
