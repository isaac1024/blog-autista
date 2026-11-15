<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * What checking the address of an external resource found: that it leads
 * to the resource, that the resource is gone or no longer public, that it
 * leads somewhere else now, or nothing sure, such as a server that turns
 * robots away or doesn't answer.
 */
final readonly class CheckOutcome
{
    /**
     * @param ?int    $status  the status of the answer, null when none came
     * @param ?string $movedTo where the address leads now, when it moved
     */
    private function __construct(
        public CheckResult $result,
        public ?int $status,
        public ?string $movedTo,
    ) {}

    public static function available(int $status): self
    {
        return new self(CheckResult::Available, $status, null);
    }

    public static function unavailable(int $status): self
    {
        return new self(CheckResult::Unavailable, $status, null);
    }

    public static function moved(int $status, string $movedTo): self
    {
        return new self(CheckResult::Moved, $status, $movedTo);
    }

    /**
     * @param ?int $status null when the server never answered
     */
    public static function doubtful(?int $status): self
    {
        return new self(CheckResult::Doubtful, $status, null);
    }

    /**
     * The availability the outcome tells, or null when it tells none: a
     * resource that moved still exists, so it stays as it was until an
     * editor gives it its new address.
     */
    public function availability(): ?Availability
    {
        return match ($this->result) {
            CheckResult::Available => Availability::Available,
            CheckResult::Unavailable => Availability::Unavailable,
            CheckResult::Moved, CheckResult::Doubtful => null,
        };
    }
}
