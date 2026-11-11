<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Domain;

use Cms\Relation\Domain\CmsPageImagesUsed;

/**
 * Builds the event of a page telling the images it embeds, as relations take it.
 */
final class CmsPageImagesUsedMother
{
    public const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    public const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    public const string OTHER_IMAGE = '01a1b801-ce58-723d-8234-b76d9105d002';

    /**
     * @param list<string> $imageIds
     */
    public static function create(string $page = self::PAGE, array $imageIds = [self::IMAGE]): CmsPageImagesUsed
    {
        return new CmsPageImagesUsed($page, $imageIds);
    }
}
