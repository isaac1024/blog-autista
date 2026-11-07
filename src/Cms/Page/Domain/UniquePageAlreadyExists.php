<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class UniquePageAlreadyExists extends DomainError
{
    public static function ofType(PageType $type, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The site already has its "%s" page.', $type->value), 'errors.page.unique_already_exists', ['%type%' => $type->value], $previous);
    }
}
