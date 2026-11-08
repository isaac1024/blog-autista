<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence\Doctrine;

use Cms\Settings\Domain\SettingsId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the SettingsId value object to a native UUID column: the identity of the settings, so it can be the identifier.
 *
 * @extends UuidType<SettingsId>
 */
final class SettingsIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return SettingsId::class;
    }
}
