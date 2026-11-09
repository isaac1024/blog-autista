<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

/**
 * What the page sends of a visit, every field always there, empty when the
 * browser had none of it.
 */
final readonly class VisitPayload
{
    public function __construct(
        public string $page,
        public string $referrer,
        public string $source,
        public string $medium,
        public string $zone,
    ) {}
}
