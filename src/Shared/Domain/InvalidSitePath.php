<?php

declare(strict_types=1);

namespace Shared\Domain;

final class InvalidSitePath extends DomainError
{
    public static function notAbsolute(string $url): self
    {
        return new self(\sprintf('"%s" is not an absolute address.', $url), 'errors.site_path.not_absolute', ['%url%' => $url]);
    }

    public static function otherHost(string $url, string $host): self
    {
        return new self(\sprintf('"%s" is not an address of %s.', $url, $host), 'errors.site_path.other_host', ['%url%' => $url, '%host%' => $host]);
    }

    public static function root(): self
    {
        return new self('The root of the site is the home page, always there.', 'errors.site_path.root', []);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The address must not exceed %d characters.', $maxLength), 'errors.site_path.too_long', ['%max%' => $maxLength]);
    }
}
