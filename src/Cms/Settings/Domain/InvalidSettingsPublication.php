<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\DomainError;

final class InvalidSettingsPublication extends DomainError
{
    public static function nothingToPublish(): self
    {
        return new self('The settings are published as they are, so there is nothing to publish.', 'errors.settings.publication.nothing_to_publish', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only published settings with unpublished changes have changes to discard.', 'errors.settings.publication.no_changes_to_discard', []);
    }
}
