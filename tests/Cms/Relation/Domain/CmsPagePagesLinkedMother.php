<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Domain;

use Cms\Relation\Domain\CmsPagePagesLinked;

/**
 * Builds the event of a page telling the pages it links to, as relations take it.
 */
final class CmsPagePagesLinkedMother
{
    public const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';
    public const string LINKED = '01a1b801-ce58-723d-8234-b76d9105e001';
    public const string OTHER_LINKED = '01a1b801-ce58-723d-8234-b76d9105e002';

    /**
     * @param list<string> $pageIds
     */
    public static function create(string $page = self::PAGE, array $pageIds = [self::LINKED]): CmsPagePagesLinked
    {
        return new CmsPagePagesLinked($page, $pageIds);
    }
}
