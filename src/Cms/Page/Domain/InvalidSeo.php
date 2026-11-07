<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidSeo extends DomainError
{
    public static function descriptionTooLong(int $maxLength): self
    {
        return new self(\sprintf('The meta description must not exceed %d characters.', $maxLength));
    }

    public static function emptyDescription(): self
    {
        return new self('A meta description, when there is one, must not be empty.');
    }

    public static function unknownRobots(string $value): self
    {
        return new self(\sprintf('"%s" is not a robots directive.', $value));
    }
}
