<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

/**
 * A redirect as the site reads it, from the CMS that keeps it: where its
 * address leads, a resource or nowhere, and the resource whose last address
 * it was, if any. The site never changes one; the CMS answers for them.
 */
final readonly class Redirect
{
    /**
     * @param ?string $targetType "page", "image", "post", "term" or "author", null when the address is gone
     * @param ?string $originId   the resource whose last address it is, null for none
     */
    public function __construct(
        private ?string $targetType,
        private ?string $targetId,
        private ?string $originId,
    ) {}

    public function targetType(): ?string
    {
        return $this->targetType;
    }

    public function targetId(): ?string
    {
        return $this->targetId;
    }

    public function originId(): ?string
    {
        return $this->originId;
    }
}
