<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

use Shared\Domain\DomainError;

final class InvalidPeriod extends DomainError
{
    public static function ofDays(int $days): self
    {
        return new self(\sprintf('The report covers no period of %d days.', $days), 'errors.analytics.unknown_period', ['%days%' => $days]);
    }
}
