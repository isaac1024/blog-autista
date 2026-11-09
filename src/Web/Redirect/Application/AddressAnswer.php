<?php

declare(strict_types=1);

namespace Web\Redirect\Application;

/**
 * How the site answers an address it has nothing of its own at: with the
 * address of what is there now, or saying what was there is gone.
 */
final readonly class AddressAnswer
{
    /**
     * @param ?string $location where it leads, relative to the root of the site; null when it is gone
     */
    private function __construct(public ?string $location) {}

    public static function movedTo(string $location): self
    {
        return new self($location);
    }

    public static function gone(): self
    {
        return new self(null);
    }
}
