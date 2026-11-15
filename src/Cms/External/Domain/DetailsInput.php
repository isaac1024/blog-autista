<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Reads the details of an external resource from what was written in its
 * form or stored, where every detail may come as text.
 */
final readonly class DetailsInput
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * The text, a single line or several, trimmed; null when blank.
     */
    public function text(string $name): ?string
    {
        $value = $this->data[$name] ?? null;

        if (!\is_string($value)) {
            return null;
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return '' === $value ? null : $value;
    }

    /**
     * A whole number written as digits, or null when blank.
     */
    public function number(string $name): ?int
    {
        $value = $this->data[$name] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        $value = $this->text($name);

        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^[0-9]{1,12}$/', $value)) {
            throw InvalidResourceDetails::notANumber($name);
        }

        return (int) $value;
    }

    /**
     * A day written as Y-m-d, or null when blank.
     */
    public function date(string $name): ?\DateTimeImmutable
    {
        $value = $this->text($name);

        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw InvalidResourceDetails::notADate($name);
        }

        return $date;
    }

    /**
     * Whether a box was ticked, as a form or the store says it.
     */
    public function flag(string $name): bool
    {
        $value = $this->data[$name] ?? null;

        return true === $value || '1' === $value || 'on' === $value;
    }
}
