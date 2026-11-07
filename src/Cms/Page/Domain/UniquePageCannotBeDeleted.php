<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class UniquePageCannotBeDeleted extends DomainError
{
    public static function ofType(PageType $type): self
    {
        return new self(\sprintf('The "%s" page is unique, so it cannot be deleted.', $type->value), 'errors.page.unique_cannot_be_deleted', []);
    }
}
