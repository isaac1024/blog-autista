<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Domain;

use Cms\Redirect\Domain\Origin;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Redirect\Domain\Timeline;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds Redirect aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     RedirectMother::create()
 *     RedirectMother::create(source: '/otra-vieja.html', target: Resource::page(RedirectMother::PAGE))
 *     RedirectMother::gone(origin: Resource::page(RedirectMother::OFF_SITE))
 *
 * A redirect answers /vieja.html, leads to RedirectMother::PAGE, knows no
 * origin, and is created and changed at ClockMother::NOW, unless a test says
 * otherwise; it is changed when it is created unless updatedAt says otherwise.
 */
final class RedirectMother
{
    public const string SOURCE = '/vieja.html';
    public const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    public const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';
    public const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';

    /**
     * Leading to the target given, or to RedirectMother::PAGE.
     */
    public static function create(
        string $id = RedirectIdMother::DEFAULT_VALUE,
        string $source = self::SOURCE,
        ?Resource $target = null,
        ?Resource $origin = null,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
    ): Redirect {
        return self::build($id, $source, Target::leadingTo($target ?? Resource::page(self::PAGE)), $origin, $createdAt, $updatedAt);
    }

    /**
     * One that says its address is gone.
     */
    public static function gone(
        string $id = RedirectIdMother::DEFAULT_VALUE,
        string $source = self::SOURCE,
        ?Resource $origin = null,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
    ): Redirect {
        return self::build($id, $source, Target::gone(), $origin, $createdAt, $updatedAt);
    }

    private static function build(string $id, string $source, Target $target, ?Resource $origin, string $createdAt, ?string $updatedAt): Redirect
    {
        return new Redirect(
            RedirectIdMother::create($id),
            Source::fromStored($source),
            $target,
            null === $origin ? Origin::none() : Origin::from($origin),
            new Timeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt)),
        );
    }
}
