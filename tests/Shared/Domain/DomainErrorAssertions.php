<?php

declare(strict_types=1);

namespace Shared\Tests\Domain;

use Shared\Domain\DomainError;

/**
 * Checks what a domain error tells people: its key and parameters, spelt out
 * in the test rather than built by the code that throws it, so a test catches
 * either of them changing.
 */
trait DomainErrorAssertions
{
    /**
     * @param array<string, int|string> $parameters
     */
    private static function assertTells(string $key, array $parameters, \Closure $action): void
    {
        try {
            $action();
        } catch (DomainError $error) {
            self::assertSame([$key, $parameters], [$error->key(), $error->parameters()]);

            return;
        }

        self::fail(\sprintf('No domain error told "%s".', $key));
    }
}
