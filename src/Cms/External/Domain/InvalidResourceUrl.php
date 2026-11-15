<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\DomainError;

final class InvalidResourceUrl extends DomainError
{
    public static function notAnAddress(): self
    {
        return new self('The address of an external resource must be an absolute address over HTTP or HTTPS.', 'errors.external.url.not_an_address', []);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The address of an external resource must not exceed %d characters.', $maxLength), 'errors.external.url.too_long', ['%max%' => $maxLength]);
    }

    public static function ownSite(): self
    {
        return new self('An address of the blog itself is linked as the content it is, not as an external resource.', 'errors.external.url.own_site', []);
    }

    public static function anotherKind(): self
    {
        return new self('The address leads to another kind of resource; create a new external resource for it.', 'errors.external.url.another_kind', []);
    }

    public static function taken(string $id): self
    {
        return new self('Another external resource has this address already.', 'errors.external.url.taken', ['%id%' => $id]);
    }
}
