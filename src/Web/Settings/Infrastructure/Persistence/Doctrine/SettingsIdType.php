<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Settings\Domain\SettingsId;

/**
 * Maps the SettingsId value object to a native UUID column, so it can be the identifier.
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
