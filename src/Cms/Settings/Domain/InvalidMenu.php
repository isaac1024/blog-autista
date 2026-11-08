<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\DomainError;

final class InvalidMenu extends DomainError
{
    public static function malformed(): self
    {
        return new self('A menu is a list of entries, each with the id of a page.', 'errors.settings.menu.malformed', []);
    }

    public static function tooManyItems(int $maxItems): self
    {
        return new self(\sprintf('A menu must not have more than %d entries.', $maxItems), 'errors.settings.menu.too_many_items', ['%max%' => $maxItems]);
    }

    public static function repeatedPage(): self
    {
        return new self('A menu leads to each page once.', 'errors.settings.menu.repeated_page', []);
    }

    public static function labelTooLong(int $maxLength): self
    {
        return new self(\sprintf('The label of a menu entry must not exceed %d characters.', $maxLength), 'errors.settings.menu.label_too_long', ['%max%' => $maxLength]);
    }
}
