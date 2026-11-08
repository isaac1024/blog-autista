<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Domain;

use Cms\Settings\Domain\SettingsId;

/**
 * Builds SettingsId value objects for tests:
 *
 *     SettingsIdMother::create()
 *     SettingsIdMother::random()
 */
final class SettingsIdMother
{
    public const string DEFAULT_VALUE = '01a1c0de-5e77-7a5e-8a11-000000000001';

    public static function create(string $value = self::DEFAULT_VALUE): SettingsId
    {
        return new SettingsId($value);
    }

    /**
     * A SettingsId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): SettingsId
    {
        return SettingsId::random();
    }
}
