<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\NarrowedTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which resource the address of a list of redirects narrows it to: a page
 * or an image by its id, as their key writes them, and nothing otherwise.
 */
final class NarrowedTargetTest extends TestCase
{
    private const string ID = '0196f3a0-0000-7000-8000-0000000000a1';

    #[DataProvider('targets')]
    public function testItReadsTheResourceTheListIsNarrowedTo(string $target, ?Resource $resource): void
    {
        self::assertEquals($resource, NarrowedTarget::fromQuery($target));
    }

    /**
     * @return iterable<string, array{string, ?resource}>
     */
    public static function targets(): iterable
    {
        yield 'a page' => ['page:'.self::ID, Resource::page(self::ID)];
        yield 'an image' => ['image:'.self::ID, Resource::image(self::ID)];
        yield 'as a resource writes its key' => [Resource::page(self::ID)->key(), Resource::page(self::ID)];
        yield 'nothing' => ['', null];
        yield 'a kind without an id' => ['page', null];
        yield 'a kind with an empty id' => ['page:', null];
        yield 'an id of no resource' => ['page:sobre-mi', null];
        yield 'an id with more after it' => ['page:'.self::ID.':otra', null];
        yield 'a kind of no resource' => ['video:'.self::ID, null];
        yield 'an id alone' => [self::ID, null];
    }
}
