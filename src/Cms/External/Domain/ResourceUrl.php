<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\WebAddress;

/**
 * Where an external resource is, written in one way whatever way it was
 * pasted, so one address names one resource: without a fragment nor the
 * parameters that only track who shared it.
 */
final readonly class ResourceUrl
{
    public const int MAX_LENGTH = 2000;

    private string $value;

    private WebAddress $address;

    public function __construct(string $value)
    {
        $address = WebAddress::fromString($value) ?? throw InvalidResourceUrl::notAnAddress();
        $this->value = $address->normalized();

        if (\strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidResourceUrl::tooLong(self::MAX_LENGTH);
        }

        $this->address = $address;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function address(): WebAddress
    {
        return $this->address;
    }

    /**
     * What the address is known by, the same for every way it was written,
     * short enough to be indexed and kept unique.
     */
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
