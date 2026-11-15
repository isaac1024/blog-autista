<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\PageTitle;

/**
 * Builds PageTitle value objects for tests:
 *
 *     PageTitleMother::create()
 *     PageTitleMother::create('Sobre mí')
 */
final class PageTitleMother
{
    public const string DEFAULT_VALUE = 'Posts';

    public static function create(string $value = self::DEFAULT_VALUE): PageTitle
    {
        return new PageTitle($value);
    }
}
