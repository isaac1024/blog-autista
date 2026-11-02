<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\PageId;

/**
 * Builds PageId value objects for tests:
 *
 *     PageIdMother::create()
 *     PageIdMother::create('0f8fad5b-d9cb-469f-a165-70867728950e')
 */
final class PageIdMother
{
    public const string DEFAULT_VALUE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    public static function create(string $value = self::DEFAULT_VALUE): PageId
    {
        return new PageId($value);
    }
}
