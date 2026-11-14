<?php

declare(strict_types=1);

namespace Web\Post\Domain;

use Shared\Domain\DomainError;

final class AddressTaken extends DomainError
{
    public static function atAddress(string $address): self
    {
        return new self(\sprintf('Another post on the site is still at "%s".', $address), 'errors.post.address.taken', ['%address%' => $address]);
    }
}
