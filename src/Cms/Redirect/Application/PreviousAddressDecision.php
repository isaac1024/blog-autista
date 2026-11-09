<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

/**
 * What an editor decides of the address a page leaves when it is published
 * at another: it leads somewhere, it stays as it is, or nothing was decided,
 * which is fine only while the page leaves none.
 */
final readonly class PreviousAddressDecision
{
    private function __construct(
        private bool $decided,
        private ?TargetInput $target,
    ) {}

    public static function undecided(): self
    {
        return new self(false, null);
    }

    /**
     * It stays as it is: its redirect, if any, or nothing, so it isn't found.
     */
    public static function keep(): self
    {
        return new self(true, null);
    }

    public static function leadTo(TargetInput $target): self
    {
        return new self(true, $target);
    }

    public function isDecided(): bool
    {
        return $this->decided;
    }

    /**
     * Where it leads, or null to leave it as it is.
     */
    public function target(): ?TargetInput
    {
        return $this->target;
    }
}
