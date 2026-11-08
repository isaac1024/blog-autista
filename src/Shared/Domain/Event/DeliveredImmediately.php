<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Marks an event that is told while the change it tells is still being made,
 * rather than queued for later: whoever keeps track of it must never be
 * behind, so a failure there fails the change too.
 */
interface DeliveredImmediately {}
