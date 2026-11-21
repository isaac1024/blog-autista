<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use Psr\Log\AbstractLogger;

/**
 * A logger that keeps the warnings it is told, their placeholders filled in.
 */
final class KeptWarnings extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $warnings = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if ('warning' !== $level) {
            return;
        }

        $placeholders = [];
        foreach ($context as $key => $value) {
            $placeholders['{'.$key.'}'] = \is_scalar($value) ? (string) $value : '';
        }

        $this->warnings[] = strtr((string) $message, $placeholders);
    }
}
