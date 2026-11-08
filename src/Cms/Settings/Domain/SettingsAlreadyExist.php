<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\DomainError;

final class SettingsAlreadyExist extends DomainError
{
    public static function create(): self
    {
        return new self('The site already has its settings.', 'errors.settings.already_exist', []);
    }
}
