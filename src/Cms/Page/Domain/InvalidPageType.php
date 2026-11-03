<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPageType extends DomainError
{
    public static function unknown(string $value): self
    {
        return new self(\sprintf('"%s" is not a page type.', $value));
    }

    public static function notUnique(PageType $type): self
    {
        return new self(\sprintf('"%s" pages are not unique, so they are created from the CMS.', $type->value));
    }
}
