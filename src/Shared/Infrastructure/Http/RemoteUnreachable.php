<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

/**
 * No answer came from another site: its host couldn't be reached in time,
 * or it is one of the private network, which is never read.
 */
final class RemoteUnreachable extends \RuntimeException
{
    public static function reading(string $url, \Throwable $previous): self
    {
        return new self(\sprintf('"%s" could not be read: %s', $url, $previous->getMessage()), 0, $previous);
    }
}
