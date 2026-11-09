<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;

final readonly class TargetFilter extends Filter
{
    /**
     * Redirects that lead to a resource of the type.
     */
    public static function type(ResourceType $type): self
    {
        return new self('target_type', FilterOperator::Equal, $type->value);
    }

    /**
     * Redirects that lead to the resource with the id, whatever its type:
     * ids are never shared, so with the type, it names one resource.
     */
    public static function id(string $id): self
    {
        return new self('target_id', FilterOperator::Equal, $id);
    }
}
