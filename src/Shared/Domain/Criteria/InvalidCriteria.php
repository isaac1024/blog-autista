<?php

declare(strict_types=1);

namespace Shared\Domain\Criteria;

use Shared\Domain\DomainError;

final class InvalidCriteria extends DomainError
{
    public static function nonPositiveLimit(int $limit): self
    {
        return new self(\sprintf('The criteria limit must be positive, %d given.', $limit), 'errors.criteria.non_positive_limit', ['%limit%' => $limit]);
    }

    public static function limitTooHigh(int $limit, int $maxLimit): self
    {
        return new self(\sprintf('The criteria limit must not exceed %d, %d given.', $maxLimit, $limit), 'errors.criteria.limit_too_high', ['%limit%' => $limit, '%max%' => $maxLimit]);
    }

    public static function negativeOffset(int $offset): self
    {
        return new self(\sprintf('The criteria offset must not be negative, %d given.', $offset), 'errors.criteria.negative_offset', ['%offset%' => $offset]);
    }

    public static function nonPositivePage(int $page): self
    {
        return new self(\sprintf('The page number must be positive, %d given.', $page), 'errors.criteria.non_positive_page', ['%page%' => $page]);
    }

    public static function unknownField(string $field): self
    {
        return new self(\sprintf('"%s" is not a field the criteria can filter or sort by.', $field), 'errors.criteria.unknown_field', ['%field%' => $field]);
    }
}
