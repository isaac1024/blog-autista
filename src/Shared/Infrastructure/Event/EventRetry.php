<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Event;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Tells the queue to hand the event over again later, as one of the retries
 * its transport allows, for a subscriber that can't take it yet but will once
 * another event arrives.
 */
final class EventRetry extends RecoverableMessageHandlingException
{
    public static function after(int $milliseconds, \Throwable $reason): self
    {
        return new self($reason->getMessage(), 0, $reason, $milliseconds, false);
    }
}
