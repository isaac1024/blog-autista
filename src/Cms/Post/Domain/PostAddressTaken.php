<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

final class PostAddressTaken extends DomainError
{
    public static function atAddress(PostAddress $address, ?\Throwable $previous = null): self
    {
        return new self(
            \sprintf('Another post on the site is addressed by "%s".', $address->key()),
            'errors.post.address.taken',
            ['%slug%' => $address->slug()->value(), '%day%' => $address->day()],
            $previous,
        );
    }
}
