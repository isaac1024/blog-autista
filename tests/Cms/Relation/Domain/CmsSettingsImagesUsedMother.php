<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Domain;

use Cms\Relation\Domain\CmsSettingsImagesUsed;

/**
 * Builds the event of the settings telling the images they use, as relations take it.
 */
final class CmsSettingsImagesUsedMother
{
    public const string SETTINGS = '01a1c0de-5e77-7a5e-8a11-000000000001';

    public const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    public const string OTHER_IMAGE = '01a1b801-ce58-723d-8234-b76d9105d002';

    /**
     * @param list<string> $imageIds
     */
    public static function create(string $settings = self::SETTINGS, array $imageIds = [self::IMAGE]): CmsSettingsImagesUsed
    {
        return new CmsSettingsImagesUsed($settings, $imageIds);
    }
}
