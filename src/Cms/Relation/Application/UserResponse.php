<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\Relation;

/**
 * A resource of the CMS that uses another: its type and its id.
 */
final readonly class UserResponse
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    public static function fromRelation(Relation $relation): self
    {
        return new self($relation->sourceType()->value, $relation->sourceId());
    }
}
