<?php

declare(strict_types=1);

namespace Web\Tests\Page\Domain;

use Web\Page\Domain\PageId;

/**
 * Builds PageId value objects for tests:
 *
 *     PageIdMother::create()
 *     PageIdMother::random()
 */
final class PageIdMother
{
    public const string DEFAULT_VALUE = '01a1a3cd-5645-7e4e-aee9-695e7c827771';

    public static function create(string $value = self::DEFAULT_VALUE): PageId
    {
        return new PageId($value);
    }

    public static function random(): PageId
    {
        return PageId::random();
    }
}
