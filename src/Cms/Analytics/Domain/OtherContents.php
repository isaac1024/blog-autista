<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * The contents seen over some days that the report doesn't show apart: how
 * many they are, and what they add up to.
 */
final readonly class OtherContents
{
    public function __construct(
        public int $count,
        public int $views,
        public int $visitors,
    ) {}
}
