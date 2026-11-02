<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\Title;

/**
 * Builds Title value objects for tests:
 *
 *     TitleMother::create()
 *     TitleMother::create('Sobre mí')
 */
final class TitleMother
{
    public const string DEFAULT_VALUE = 'Posts';

    public static function create(string $value = self::DEFAULT_VALUE): Title
    {
        return new Title($value);
    }
}
