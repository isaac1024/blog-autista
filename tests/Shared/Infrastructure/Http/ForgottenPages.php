<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use Shared\Domain\PageCache;

/**
 * The page cache of the tests: it keeps nothing, and remembers the addresses
 * it was told to forget, each test reading them from an empty list.
 */
final class ForgottenPages implements PageCache
{
    /**
     * @var list<string>
     */
    private static array $forgotten = [];

    #[\Override]
    public function forget(string $path): void
    {
        self::$forgotten[] = $path;
    }

    /**
     * The addresses forgotten since the last call, which starts the list anew.
     *
     * @return list<string>
     */
    public static function take(): array
    {
        $forgotten = self::$forgotten;
        self::$forgotten = [];

        return $forgotten;
    }
}
