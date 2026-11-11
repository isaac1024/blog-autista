<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Domain;

use Web\Redirect\Domain\Redirect;

/**
 * Builds the redirects the site reads, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     RedirectMother::create()
 *     RedirectMother::create(targetType: 'image', targetId: RedirectMother::IMAGE)
 *     RedirectMother::gone(originId: RedirectMother::ORIGIN)
 *
 * A redirect leads to RedirectMother::PAGE and knows no origin, unless a
 * test says otherwise.
 */
final class RedirectMother
{
    public const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    public const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    public const string ORIGIN = '0196f3a0-0000-7000-8000-0000000000a2';

    public static function create(string $targetType = 'page', string $targetId = self::PAGE, ?string $originId = null): Redirect
    {
        return new Redirect($targetType, $targetId, $originId);
    }

    /**
     * One that says its address is gone.
     */
    public static function gone(?string $originId = null): Redirect
    {
        return new Redirect(null, null, $originId);
    }
}
