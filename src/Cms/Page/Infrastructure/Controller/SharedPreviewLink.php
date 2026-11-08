<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

/**
 * A signed link to the preview of a page, for anyone who has it, and when it
 * stops working.
 */
final readonly class SharedPreviewLink
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
