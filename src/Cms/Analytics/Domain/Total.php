<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * What a value of a dimension adds up to over some days: the pages seen,
 * and the readers of each day, summed. A null value groups those too small
 * to show apart, or a country unknown.
 */
final readonly class Total
{
    public function __construct(
        public ?string $value,
        public int $views,
        public int $visitors,
    ) {}
}
