<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

use Shared\Domain\DomainError;

final class InvalidPublicationTimeline extends DomainError
{
    public static function incompleteDates(): self
    {
        return new self('What was published once has both a first and a last publication date.', 'errors.publication.incomplete_dates', []);
    }
}
