<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\DomainError;

final class InvalidResourceDetails extends DomainError
{
    public static function tooLong(string $detail, int $maxLength): self
    {
        return new self(\sprintf('The detail "%s" of an external resource must not exceed %d characters.', $detail, $maxLength), 'errors.external.details.too_long', ['%detail%' => $detail, '%max%' => $maxLength]);
    }

    public static function singleLine(string $detail): self
    {
        return new self(\sprintf('The detail "%s" of an external resource is a single line.', $detail), 'errors.external.details.single_line', ['%detail%' => $detail]);
    }

    public static function notANumber(string $detail): self
    {
        return new self(\sprintf('The detail "%s" of an external resource is a whole number.', $detail), 'errors.external.details.not_a_number', ['%detail%' => $detail]);
    }

    public static function notADate(string $detail): self
    {
        return new self(\sprintf('The detail "%s" of an external resource is a day.', $detail), 'errors.external.details.not_a_date', ['%detail%' => $detail]);
    }

    public static function yearOutOfRange(int $min, int $max): self
    {
        return new self(\sprintf('The year of a study is between %d and %d.', $min, $max), 'errors.external.details.year_out_of_range', ['%min%' => $min, '%max%' => $max]);
    }
}
