<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Domain;

use Cms\Page\Domain\Slug;

/**
 * Builds Slug value objects for tests:
 *
 *     SlugMother::create()
 *     SlugMother::create('sobre-mi')
 */
final class SlugMother
{
    public const string DEFAULT_VALUE = 'posts';

    public static function create(string $value = self::DEFAULT_VALUE): Slug
    {
        return new Slug($value);
    }
}
