<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Domain;

use Cms\Relation\Domain\CmsImagePagesLinked;

/**
 * Builds the event of an image telling the pages its caption links to, as
 * relations take it.
 */
final class CmsImagePagesLinkedMother
{
    public const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';
    public const string LINKED = '01a1b801-ce58-723d-8234-b76d9105e001';
    public const string OTHER_LINKED = '01a1b801-ce58-723d-8234-b76d9105e002';

    /**
     * @param list<string> $pageIds
     */
    public static function create(string $image = self::IMAGE, array $pageIds = [self::LINKED]): CmsImagePagesLinked
    {
        return new CmsImagePagesLinked($image, $pageIds);
    }
}
